# Documentation Détaillée du Microservice d'Authentification

## Table des Matières
1. [Architecture Globale](#architecture-globale)
2. [Couche Domain (Domaine)](#couche-domain)
3. [Couche Application](#couche-application)
4. [Couche Infrastructure](#couche-infrastructure)
5. [Couche Presentation (Présentation)](#couche-presentation)
6. [Patterns et Concepts Avancés](#patterns-et-concepts-avancés)
7. [Flux de Données](#flux-de-données)

---

## Architecture Globale

Ce projet suit l'**Architecture Propre (Clean Architecture)** avec une séparation stricte en 4 couches :

```
┌─────────────────────────────────────────┐
│      Presentation Layer (API)           │
│  (Controllers, Exception Handlers)      │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│      Application Layer                  │
│  (Services, DTOs, Commands)             │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│      Domain Layer (Cœur Métier)         │
│  (Entities, Value Objects, Events)      │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│      Infrastructure Layer               │
│  (Repositories, Messaging, Security)    │
└─────────────────────────────────────────┘
```

---

## Couche Domain (Domaine)

### 1. Entités (Entities)

#### **User.php** - Entité Utilisateur
**Rôle** : Représente un utilisateur dans le système avec toutes ses propriétés et comportements métier.

**Responsabilités** :
- Stocker les informations de l'utilisateur (id, email, mot de passe, nom, rôles)
- Gérer le cycle de vie de l'utilisateur (création, modification, suppression logique)
- Vérifier les mots de passe
- Gérer les rôles utilisateur
- Implémenter les interfaces Symfony pour l'authentification

**Propriétés principales** :
```php
- id: string (UUID)                    // Identifiant unique
- email: string                        // Email (unique)
- password: string                     // Mot de passe hashé
- name: string                         // Nom complet
- roles: array                         // Rôles de sécurité
- createdAt: DateTimeImmutable         // Date de création
- updatedAt: DateTimeImmutable         // Date de modification
- deletedAt: ?DateTimeImmutable        // Date de suppression (soft delete)
```

**Méthodes clés** :
- `__construct()` : Crée un nouvel utilisateur avec validation des Value Objects
- `verifyPassword()` : Vérifie si un mot de passe en clair correspond au hash stocké
- `changePassword()` : Change le mot de passe de l'utilisateur
- `addRole()` / `removeRole()` : Gestion des rôles
- `softDelete()` : Suppression logique (marque comme supprimé sans effacer)
- `isDeleted()` : Vérifie si l'utilisateur est supprimé

**Pourquoi c'est important** :
- Encapsule toute la logique métier liée aux utilisateurs
- Garantit l'intégrité des données grâce aux Value Objects
- Implémente le pattern Entity du DDD (Domain-Driven Design)

---

#### **OutboxEvent.php** - Événement Outbox
**Rôle** : Représente un événement qui doit être publié vers d'autres microservices via RabbitMQ.

**Responsabilités** :
- Stocker les événements en attente de publication
- Suivre le statut de publication (PENDING, PROCESSED, FAILED)
- Gérer les tentatives de republication en cas d'échec
- Implémenter le pattern Outbox pour garantir la cohérence éventuelle

**Propriétés principales** :
```php
- id: string (UUID)                    // Identifiant unique de l'événement
- aggregateId: string                  // ID de l'entité concernée (ex: userId)
- aggregateType: string                // Type d'entité (ex: "User")
- eventType: string                    // Type d'événement (ex: "UserCreated")
- payload: array                       // Données de l'événement (JSON)
- occurredAt: DateTimeImmutable        // Quand l'événement s'est produit
- processedAt: ?DateTimeImmutable      // Quand il a été publié
- status: OutboxStatus                 // PENDING, PROCESSED, FAILED
- retryCount: int                      // Nombre de tentatives
- errorMessage: ?string                // Message d'erreur si échec
```

**Méthodes clés** :
- `markAsProcessed()` : Marque l'événement comme publié avec succès
- `markAsFailed()` : Marque l'événement comme échoué et incrémente le compteur
- `resetForRetry()` : Réinitialise pour une nouvelle tentative
- `canRetry()` : Vérifie si on peut encore réessayer (max 3 fois)

**Pourquoi c'est important** :
- Garantit qu'aucun événement n'est perdu même si RabbitMQ est indisponible
- Permet la traçabilité complète des événements
- Implémente le pattern Transactional Outbox pour la cohérence éventuelle

---

### 2. Value Objects (Objets Valeur)

#### **Email.php** - Objet Valeur Email
**Rôle** : Encapsule et valide une adresse email.

**Responsabilités** :
- Valider le format de l'email
- Normaliser l'email (minuscules, trim)
- Garantir l'immutabilité
- Fournir des méthodes de comparaison

**Caractéristiques** :
```php
- Immutable (readonly class)
- Validation automatique avec filter_var()
- Normalisation automatique (lowercase + trim)
```

**Méthodes** :
- `fromString()` : Crée un Email depuis une chaîne (avec validation)
- `getValue()` : Retourne la valeur de l'email
- `equals()` : Compare deux emails
- `__toString()` : Conversion en chaîne

**Pourquoi c'est important** :
- Empêche les emails invalides dans le système
- Centralise la logique de validation
- Garantit la cohérence des données

---

#### **Password.php** - Objet Valeur Mot de Passe
**Rôle** : Gère le hachage sécurisé et la validation des mots de passe.

**Responsabilités** :
- Valider la complexité du mot de passe
- Hasher les mots de passe avec Argon2ID
- Vérifier les mots de passe
- Détecter si un rehash est nécessaire

**Règles de validation** :
```php
- Minimum 8 caractères
- Au moins 1 majuscule
- Au moins 1 minuscule
- Au moins 1 chiffre
- Au moins 1 caractère spécial (@$!%*?&)
```

**Configuration Argon2ID** :
```php
- memory_cost: 65536 (64 MB)
- time_cost: 4 itérations
- threads: 3 threads parallèles
```

**Méthodes** :
- `fromPlain()` : Crée un Password depuis un mot de passe en clair (hash + validation)
- `fromHash()` : Crée un Password depuis un hash existant
- `verify()` : Vérifie si un mot de passe en clair correspond au hash
- `getHash()` : Retourne le hash
- `needsRehash()` : Vérifie si le hash doit être recalculé (sécurité)

**Pourquoi c'est important** :
- Sécurité maximale avec Argon2ID (résistant aux attaques GPU)
- Validation stricte des mots de passe
- Empêche le stockage de mots de passe en clair

---

#### **UserId.php** - Objet Valeur Identifiant Utilisateur
**Rôle** : Encapsule un identifiant UUID d'utilisateur.

**Responsabilités** :
- Générer des UUID v4 uniques
- Valider les UUID
- Garantir l'immutabilité
- Fournir des méthodes de comparaison

**Méthodes** :
- `generate()` : Génère un nouvel UUID v4
- `fromString()` : Crée un UserId depuis une chaîne UUID
- `getValue()` : Retourne la valeur de l'UUID
- `equals()` : Compare deux UserId

**Pourquoi c'est important** :
- UUID garantit l'unicité globale (même en système distribué)
- Type-safety : impossible de confondre avec d'autres IDs
- Validation automatique du format

---

### 3. Événements (Events)

#### **UserCreatedEvent.php** - Événement Utilisateur Créé
**Rôle** : Représente l'événement métier "un utilisateur a été créé".

**Responsabilités** :
- Transporter les données de l'événement
- Être sérialisable pour RabbitMQ
- Être immutable (readonly)

**Données transportées** :
```php
- userId: string           // ID de l'utilisateur créé
- email: string            // Email de l'utilisateur
- name: string             // Nom de l'utilisateur
- roles: array             // Rôles assignés
- occurredAt: DateTimeImmutable  // Timestamp de l'événement
```

**Méthodes** :
- Getters pour toutes les propriétés
- `toArray()` : Convertit l'événement en tableau pour sérialisation

**Pourquoi c'est important** :
- Permet aux autres microservices de réagir à la création d'utilisateurs
- Découple les services (architecture événementielle)
- Traçabilité complète des événements métier

---

### 4. Énumérations (Enums)

#### **CircuitState.php** - États du Circuit Breaker
**Rôle** : Définit les 3 états possibles du Circuit Breaker.

**États** :
- `CLOSED` : Circuit fermé, tout fonctionne normalement
- `OPEN` : Circuit ouvert, trop d'échecs, rejette toutes les requêtes
- `HALF_OPEN` : Circuit semi-ouvert, teste si le service est revenu

**Méthodes** :
- `isClosed()`, `isOpen()`, `isHalfOpen()` : Vérifications d'état

---

#### **OutboxStatus.php** - Statuts des Événements Outbox
**Rôle** : Définit les 3 statuts possibles d'un événement outbox.

**Statuts** :
- `PENDING` : En attente de publication
- `PROCESSED` : Publié avec succès
- `FAILED` : Échec de publication

**Méthodes** :
- `isPending()`, `isProcessed()`, `isFailed()` : Vérifications de statut

---

### 5. Interfaces Repository

#### **IUserRepository.php** - Interface Repository Utilisateur
**Rôle** : Définit le contrat pour la persistance des utilisateurs.

**Méthodes** :
```php
- save(User $user): void                    // Sauvegarde un utilisateur
- findById(UserId $id): ?User               // Trouve par ID
- findByEmail(Email $email): ?User          // Trouve par email
- existsByEmail(Email $email): bool         // Vérifie l'existence
- delete(User $user): void                  // Supprime un utilisateur
```

**Pourquoi c'est important** :
- Inversion de dépendance (Domain ne dépend pas de l'Infrastructure)
- Facilite les tests (mock facile)
- Permet de changer l'implémentation sans toucher au Domain

---

#### **IOutboxRepository.php** - Interface Repository Outbox
**Rôle** : Définit le contrat pour la persistance des événements outbox.

**Méthodes** :
```php
- save(OutboxEvent $event): void                           // Sauvegarde un événement
- findPendingEvents(int $limit): array                     // Trouve les événements en attente
- markAsProcessed(OutboxEvent $event): void                // Marque comme publié
- markAsFailed(OutboxEvent $event, string $error): void    // Marque comme échoué
```

---

## Couche Application

### 1. Services

#### **RegistrationService.php** - Service d'Inscription
**Rôle** : Orchestre le processus d'inscription d'un nouvel utilisateur.

**Responsabilités** :
- Valider que l'email n'existe pas déjà
- Créer l'entité User
- Créer l'événement OutboxEvent
- Sauvegarder les deux dans une transaction atomique
- Logger les opérations

**Flux d'exécution** :
```
1. Valider l'email (format + unicité)
2. Démarrer une transaction
3. Créer l'utilisateur (avec hash du mot de passe)
4. Créer l'événement UserCreatedEvent
5. Créer l'OutboxEvent
6. Sauvegarder User et OutboxEvent (atomique)
7. Commit de la transaction
8. Logger le succès
```

**Gestion des erreurs** :
- Si l'email existe : `ValidationException`
- Si erreur durant la transaction : rollback automatique
- Logging de toutes les erreurs

**Pourquoi c'est important** :
- Garantit la cohérence des données (transaction)
- Implémente le pattern Outbox correctement
- Centralise la logique d'inscription

---

#### **AuthenticationService.php** - Service d'Authentification
**Rôle** : Gère le processus d'authentification des utilisateurs.

**Responsabilités** :
- Vérifier les identifiants (email + mot de passe)
- Vérifier que l'utilisateur n'est pas supprimé
- Générer les tokens JWT
- Logger les tentatives d'authentification

**Flux d'exécution** :
```
1. Chercher l'utilisateur par email
2. Vérifier que l'utilisateur existe
3. Vérifier le mot de passe
4. Vérifier que l'utilisateur n'est pas supprimé
5. Générer les tokens (access + refresh)
6. Logger le succès
7. Retourner les tokens
```

**Sécurité** :
- Messages d'erreur génériques ("Invalid credentials") pour éviter l'énumération
- Logging de toutes les tentatives (succès et échecs)
- Vérification du statut de suppression

**Pourquoi c'est important** :
- Centralise toute la logique d'authentification
- Sécurise contre les attaques courantes
- Traçabilité complète

---

#### **TokenService.php** - Service de Gestion des Tokens
**Rôle** : Génère et gère les tokens JWT (access et refresh).

**Responsabilités** :
- Créer des access tokens JWT (15 minutes de validité)
- Créer des refresh tokens (7 jours de validité)
- Rafraîchir les tokens expirés
- Gérer la rotation des refresh tokens

**Types de tokens** :

**Access Token (JWT)** :
```json
{
  "sub": "user-uuid",           // ID utilisateur
  "email": "user@example.com",  // Email
  "roles": ["ROLE_USER"],       // Rôles
  "iat": 1704451200,            // Issued at
  "exp": 1704452100,            // Expiration (15 min)
  "iss": "auth-service"         // Issuer
}
```

**Refresh Token** :
- Chaîne aléatoire de 64 bytes
- Stocké en base de données
- Usage unique (rotation à chaque refresh)
- Validité 7 jours

**Méthodes** :
- `createToken()` : Crée une paire access + refresh token
- `refreshToken()` : Rafraîchit un access token expiré

**Pourquoi c'est important** :
- Sécurité : tokens courte durée + rotation
- Stateless : access token contient toutes les infos
- Révocation possible via refresh tokens

---

#### **RateLimiter.php** - Service de Limitation de Débit
**Rôle** : Protège contre les attaques par force brute et l'abus d'API.

**Responsabilités** :
- Limiter le nombre de tentatives par période
- Stocker les compteurs dans Redis
- Calculer le temps restant avant déblocage
- Logger les dépassements de limite

**Configuration** :
```php
- maxAttempts: 5                // Maximum de tentatives
- decayMinutes: 15              // Période de réinitialisation
- Storage: Redis Cache          // Stockage des compteurs
```

**Limites appliquées** :
- Inscription : 5 tentatives / 15 min par IP
- Login : 5 tentatives / 15 min par email
- Refresh token : 10 tentatives / 15 min par IP
- Changement mot de passe : 3 tentatives / 15 min par utilisateur

**Méthodes** :
- `attempt()` : Vérifie et enregistre une tentative
- `tooManyAttempts()` : Vérifie si la limite est atteinte
- `availableIn()` : Calcule le temps restant avant déblocage
- `clear()` : Réinitialise le compteur (après succès)

**Algorithme** :
```
1. Récupérer le compteur depuis Redis
2. Si compteur >= maxAttempts : rejeter
3. Sinon : incrémenter le compteur
4. Définir l'expiration à decayMinutes
```

**Pourquoi c'est important** :
- Protection contre le brute force
- Prévention du déni de service (DoS)
- Conformité aux bonnes pratiques de sécurité

---

#### **CircuitBreaker.php** - Service Circuit Breaker
**Rôle** : Protège contre les défaillances en cascade lors de l'appel à des services externes (RabbitMQ).

**Responsabilités** :
- Surveiller les échecs d'appels externes
- Ouvrir le circuit après un seuil d'échecs
- Tester périodiquement si le service est revenu
- Logger les changements d'état

**Configuration** :
```php
- failureThreshold: 5           // Nombre d'échecs avant ouverture
- timeout: 60 secondes          // Temps avant test de récupération
- Storage: Redis Cache          // Stockage de l'état
```

**États et transitions** :
```
CLOSED (Normal)
   │
   │ 5 échecs consécutifs
   ▼
OPEN (Rejet immédiat)
   │
   │ Après 60 secondes
   ▼
HALF_OPEN (Test)
   │
   ├─ Succès ──► CLOSED
   └─ Échec ───► OPEN
```

**Méthodes** :
- `call()` : Execute une opération avec protection circuit breaker
- `isOpen()` : Vérifie si le circuit est ouvert
- `reset()` : Réinitialise manuellement le circuit

**Flux d'exécution** :
```
1. Vérifier l'état du circuit
2. Si OPEN et timeout écoulé : passer en HALF_OPEN
3. Si OPEN et timeout non écoulé : rejeter immédiatement
4. Exécuter l'opération
5. Si succès : réinitialiser les échecs (ou fermer si HALF_OPEN)
6. Si échec : incrémenter les échecs, ouvrir si seuil atteint
```

**Pourquoi c'est important** :
- Évite de surcharger un service déjà en difficulté
- Fail-fast : réponse immédiate au lieu d'attendre un timeout
- Auto-récupération : teste automatiquement si le service est revenu

---

#### **OutboxProcessor.php** - Processeur d'Événements Outbox
**Rôle** : Traite les événements en attente et les publie vers RabbitMQ.

**Responsabilités** :
- Récupérer les événements PENDING par batch
- Publier chaque événement vers RabbitMQ
- Marquer les événements comme PROCESSED ou FAILED
- Gérer les tentatives de republication
- Utiliser le Circuit Breaker pour la résilience

**Configuration** :
```php
- MAX_RETRIES: 3                // Maximum de tentatives
- BATCH_SIZE: 100               // Nombre d'événements par batch
```

**Flux d'exécution** :
```
1. Récupérer 100 événements PENDING (triés par date)
2. Pour chaque événement :
   a. Utiliser Circuit Breaker pour appeler publishEvent()
   b. Si succès : marquer comme PROCESSED
   c. Si échec et retryCount < 3 : marquer comme FAILED (réessai plus tard)
   d. Si échec et retryCount >= 3 : logger en CRITICAL (intervention manuelle)
3. Logger les résultats
```

**Gestion des erreurs** :
- Circuit Breaker protège contre RabbitMQ indisponible
- Retry automatique avec backoff exponentiel
- Logging détaillé de tous les échecs
- Dead letter queue pour les événements non récupérables

**Méthodes** :
- `processEvents()` : Traite un batch d'événements
- `publishEvent()` : Publie un événement vers RabbitMQ

**Pourquoi c'est important** :
- Garantit la livraison éventuelle de tous les événements
- Résilience face aux pannes de RabbitMQ
- Traçabilité complète des publications

---

### 2. DTOs (Data Transfer Objects)

#### **RegisterUserDTO.php** - DTO d'Inscription
**Rôle** : Transporte les données d'inscription depuis le contrôleur vers le service.

**Propriétés** :
```php
- email: string        // Email de l'utilisateur
- password: string     // Mot de passe en clair
- name: string         // Nom complet
```

**Validation** :
- Email : format valide, obligatoire
- Password : minimum 8 caractères, complexité requise
- Name : obligatoire, minimum 2 caractères

---

#### **AuthenticationDTO.php** - DTO d'Authentification
**Rôle** : Transporte les identifiants de connexion.

**Propriétés** :
```php
- email: string        // Email de l'utilisateur
- password: string     // Mot de passe en clair
```

---

#### **TokenDTO.php** - DTO de Token
**Rôle** : Transporte les tokens générés vers le client.

**Propriétés** :
```php
- accessToken: string      // JWT access token
- refreshToken: string     // Refresh token
- expiresIn: int           // Durée de validité en secondes (900)
- tokenType: string        // Type de token ("Bearer")
```

**Méthode** :
- `toArray()` : Convertit en tableau pour la réponse JSON

---

#### **ChangePasswordDTO.php** - DTO de Changement de Mot de Passe
**Rôle** : Transporte les données pour changer le mot de passe.

**Propriétés** :
```php
- currentPassword: string    // Mot de passe actuel
- newPassword: string        // Nouveau mot de passe
```

---

### 3. Commandes (Commands)

#### **ProcessOutboxEventsCommand.php** - Commande de Traitement Outbox
**Rôle** : Commande Symfony Console pour traiter les événements outbox.

**Utilisation** :
```bash
php bin/console app:process-outbox-events
```

**Fonctionnement** :
- Appelle `OutboxProcessor::processEvents()`
- Peut être exécutée manuellement ou via cron
- Recommandé : exécution toutes les 30 secondes

**Pourquoi c'est important** :
- Permet l'exécution en arrière-plan
- Peut être orchestrée par Kubernetes CronJob
- Découple la publication d'événements du flux principal

---

#### **GenerateJwtKeysCommand.php** - Commande de Génération de Clés JWT
**Rôle** : Génère les clés RSA pour signer les JWT.

**Utilisation** :
```bash
php bin/console app:generate-jwt-keys
```

**Fonctionnement** :
- Génère une paire de clés RSA 4096 bits
- Sauvegarde dans `var/jwt/private.pem` et `var/jwt/public.pem`
- Protège la clé privée avec une passphrase

**Pourquoi c'est important** :
- Sécurité : clés RSA pour signature asymétrique
- Setup initial du projet
- Rotation des clés possible

---

## Couche Infrastructure

### 1. Repositories (Implémentations)

#### **UserRepository.php** - Implémentation Repository Utilisateur
**Rôle** : Implémente `IUserRepository` avec Doctrine ORM.

**Responsabilités** :
- Persister les utilisateurs en base PostgreSQL
- Effectuer les requêtes de recherche
- Gérer les transactions

**Méthodes implémentées** :
```php
- save(): Utilise EntityManager::persist() + flush()
- findById(): Recherche par clé primaire
- findByEmail(): Recherche par email (index)
- existsByEmail(): Compte les occurrences
- delete(): Suppression physique
```

**Optimisations** :
- Index sur email pour recherche rapide
- Index sur createdAt pour tri
- Utilisation du cache Doctrine

---

#### **OutboxRepository.php** - Implémentation Repository Outbox
**Rôle** : Implémente `IOutboxRepository` avec Doctrine ORM.

**Responsabilités** :
- Persister les événements outbox
- Récupérer les événements en attente
- Mettre à jour les statuts

**Méthodes implémentées** :
```php
- save(): Persiste un événement
- findPendingEvents(): Requête avec filtre status=PENDING + tri + limite
- markAsProcessed(): Met à jour le statut + processedAt
- markAsFailed(): Met à jour le statut + retryCount + errorMessage
```

**Optimisations** :
- Index composite sur (status, occurredAt) pour requête efficace
- Index sur (aggregateId, aggregateType) pour traçabilité
- Batch processing pour performance

---

### 2. Messaging (Messagerie)

#### **UserCreatedEventHandler.php** - Gestionnaire d'Événement
**Rôle** : Gère l'événement UserCreatedEvent publié vers RabbitMQ.

**Responsabilités** :
- Recevoir l'événement depuis le bus de messages
- Logger la réception
- Peut déclencher des actions supplémentaires

**Fonctionnement** :
```
1. Symfony Messenger dispatche l'événement
2. Le transport AMQP envoie vers RabbitMQ
3. Les autres microservices consomment l'événement
4. Ce handler est appelé localement pour logging
```

**Configuration RabbitMQ** :
- Exchange : `events`
- Routing Key : `user.created`
- Queue : `auth_service_events`

**Pourquoi c'est important** :
- Découplage des microservices
- Communication asynchrone
- Scalabilité horizontale

---

### 3. Security (Sécurité)

#### **UserProvider.php** - Fournisseur d'Utilisateurs
**Rôle** : Implémente l'interface Symfony UserProviderInterface.

**Responsabilités** :
- Charger les utilisateurs pour l'authentification Symfony
- Rafraîchir les utilisateurs depuis la base
- Supporter l'authentification JWT

**Méthodes** :
```php
- loadUserByIdentifier(): Charge un utilisateur par email
- refreshUser(): Recharge un utilisateur depuis la base
- supportsClass(): Vérifie si la classe User est supportée
```

**Pourquoi c'est important** :
- Intégration avec le système de sécurité Symfony
- Nécessaire pour JWT authentication
- Gestion du cycle de vie des utilisateurs authentifiés

---

## Couche Presentation (Présentation)

### 1. Controllers (Contrôleurs)

#### **AuthController.php** - Contrôleur d'Authentification
**Rôle** : Expose les endpoints API pour l'authentification.

**Endpoints** :

**POST /api/register** - Inscription
```
Entrée : { email, password, name }
Sortie : { user: {...}, token: {...} }
Rate Limit : 5 tentatives / 15 min par IP
```

**Flux** :
1. Vérifier rate limit
2. Valider le DTO
3. Appeler RegistrationService
4. Générer les tokens
5. Retourner user + tokens (201 Created)

---

**POST /api/login** - Connexion
```
Entrée : { email, password }
Sortie : { accessToken, refreshToken, expiresIn, tokenType }
Rate Limit : 5 tentatives / 15 min par email
```

**Flux** :
1. Vérifier rate limit
2. Valider le DTO
3. Appeler AuthenticationService
4. Réinitialiser rate limit si succès
5. Retourner les tokens (200 OK)

---

**POST /api/token/refresh** - Rafraîchissement de Token
```
Entrée : { refreshToken }
Sortie : { accessToken, refreshToken, expiresIn, tokenType }
Rate Limit : 10 tentatives / 15 min par IP
```

**Flux** :
1. Vérifier rate limit
2. Valider le refresh token
3. Appeler TokenService.refreshToken()
4. Retourner les nouveaux tokens (200 OK)

---

**POST /api/logout** - Déconnexion
```
Entrée : Aucune
Sortie : { message: "Logged out successfully" }
```

**Note** : Dans une architecture JWT stateless, la déconnexion est gérée côté client (suppression du token). Une implémentation complète pourrait inclure une blacklist de tokens.

---

#### **HealthController.php** - Contrôleur de Santé
**Rôle** : Expose les endpoints de health check pour Kubernetes/Docker.

**Endpoints** :

**GET /api/health** - Health Check
```
Sortie : { status: "healthy", timestamp: "..." }
```

**GET /api/health/ready** - Readiness Probe
```
Sortie : {
  status: "ready",
  checks: {
    database: "ok",
    cache: "ok",
    messaging: "ok"
  }
}
```

**GET /api/health/live** - Liveness Probe
```
Sortie : { status: "alive" }
```

**Pourquoi c'est important** :
- Kubernetes utilise ces endpoints pour gérer les pods
- Monitoring et alerting
- Zero-downtime deployments

---

### 2. Exception Handlers (Gestionnaires d'Exceptions)

#### **GlobalExceptionHandler.php** - Gestionnaire Global d'Exceptions
**Rôle** : Intercepte toutes les exceptions et retourne des réponses JSON standardisées.

**Exceptions gérées** :

**ValidationException** (400 Bad Request)
```json
{
  "error": "Validation failed",
  "message": "User with this email already exists",
  "timestamp": "2024-01-05T10:30:00+00:00"
}
```

**AuthenticationException** (401 Unauthorized)
```json
{
  "error": "Authentication failed",
  "message": "Invalid credentials",
  "timestamp": "2024-01-05T10:30:00+00:00"
}
```

**RateLimitException** (429 Too Many Requests)
```json
{
  "error": "Rate limit exceeded",
  "message": "Too many login attempts. Please try again later.",
  "retryAfter": 900,
  "timestamp": "2024-01-05T10:30:00+00:00"
}
```

**Exception générique** (500 Internal Server Error)
```json
{
  "error": "Internal server error",
  "message": "An unexpected error occurred",
  "timestamp": "2024-01-05T10:30:00+00:00"
}
```

**Pourquoi c'est important** :
- Réponses d'erreur cohérentes
- Masque les détails techniques en production
- Logging centralisé des erreurs
- Meilleure expérience développeur

---

### 3. Custom Exceptions (Exceptions Personnalisées)

#### **ValidationException.php**
**Rôle** : Exception levée lors d'une erreur de validation métier.

**Cas d'usage** :
- Email déjà existant
- Données invalides
- Règles métier non respectées

---

#### **AuthenticationException.php**
**Rôle** : Exception levée lors d'un échec d'authentification.

**Cas d'usage** :
- Identifiants incorrects
- Utilisateur supprimé
- Token invalide

---

#### **RateLimitException.php**
**Rôle** : Exception levée lors d'un dépassement de rate limit.

**Propriétés** :
```php
- message: string          // Message d'erreur
- retryAfter: int          // Secondes avant nouvelle tentative
```

**Cas d'usage** :
- Trop de tentatives de connexion
- Trop de requêtes API
- Protection anti-brute force

---

## Patterns et Concepts Avancés

### 1. Pattern Outbox (Transactional Outbox)

**Problème résolu** :
Comment garantir qu'un événement est publié vers RabbitMQ si et seulement si la transaction en base de données réussit ?

**Solution** :
```
1. Dans une MÊME transaction :
   - Sauvegarder l'entité (User)
   - Sauvegarder l'événement (OutboxEvent)
2. Un processus en arrière-plan :
   - Lit les OutboxEvents PENDING
   - Les publie vers RabbitMQ
   - Les marque comme PROCESSED
```

**Avantages** :
- Cohérence éventuelle garantie
- Aucun événement perdu
- Résilience face aux pannes de RabbitMQ
- Traçabilité complète

**Implémentation dans le code** :
- `RegistrationService::register()` : Crée User + OutboxEvent dans une transaction
- `OutboxProcessor::processEvents()` : Publie les événements en arrière-plan
- `ProcessOutboxEventsCommand` : Commande cron pour exécuter le processeur

---

### 2. Pattern Circuit Breaker

**Problème résolu** :
Comment éviter qu'un service lent ou en panne ne ralentisse ou ne fasse tomber tout le système ?

**Solution** :
```
États du circuit :
- CLOSED : Tout va bien, les requêtes passent
- OPEN : Trop d'échecs, rejette immédiatement (fail-fast)
- HALF_OPEN : Test si le service est revenu
```

**Avantages** :
- Fail-fast : pas d'attente inutile
- Protection contre les défaillances en cascade
- Auto-récupération
- Meilleure expérience utilisateur

**Implémentation dans le code** :
- `CircuitBreaker::call()` : Enveloppe les appels à RabbitMQ
- Configuration : 5 échecs → OPEN, 60s timeout
- Utilisé par `OutboxProcessor` pour protéger la publication

---

### 3. Pattern Repository

**Problème résolu** :
Comment découpler la logique métier de la persistance des données ?

**Solution** :
```
Domain Layer : Définit l'interface IUserRepository
Infrastructure Layer : Implémente UserRepository avec Doctrine
```

**Avantages** :
- Inversion de dépendance (SOLID)
- Testabilité (mock facile)
- Changement d'implémentation sans impact sur le métier
- Abstraction de la base de données

---

### 4. Pattern Value Object

**Problème résolu** :
Comment garantir que les données sont toujours valides et cohérentes ?

**Solution** :
```
Au lieu de : string $email
Utiliser : Email $email (avec validation dans le constructeur)
```

**Avantages** :
- Validation centralisée
- Immutabilité
- Type-safety
- Impossible d'avoir des données invalides

**Exemples dans le code** :
- `Email` : Validation format + normalisation
- `Password` : Validation complexité + hashing
- `UserId` : Validation UUID + génération

---

### 5. Clean Architecture (Architecture Propre)

**Principe** :
Les dépendances vont toujours vers l'intérieur (Domain au centre).

**Règles** :
```
Domain : Ne dépend de RIEN
Application : Dépend de Domain
Infrastructure : Dépend de Domain + Application
Presentation : Dépend de Application
```

**Avantages** :
- Testabilité maximale
- Indépendance des frameworks
- Indépendance de la base de données
- Logique métier isolée et protégée

---

### 6. Event-Driven Architecture (Architecture Événementielle)

**Principe** :
Les microservices communiquent via des événements asynchrones.

**Flux** :
```
1. Auth Service : User créé → Événement UserCreated
2. RabbitMQ : Distribue l'événement
3. CRM Service : Reçoit l'événement → Crée un contact
4. ERP Service : Reçoit l'événement → Crée un employé
```

**Avantages** :
- Découplage fort
- Scalabilité
- Résilience
- Évolutivité (ajout de nouveaux consommateurs sans modification)

---

## Flux de Données

### Flux d'Inscription (Registration)

```
1. Client → POST /api/register { email, password, name }
   ↓
2. AuthController::register()
   - Vérifier rate limit (5/15min par IP)
   - Valider le DTO
   ↓
3. RegistrationService::register()
   - Vérifier unicité email
   - Démarrer transaction
   - Créer User (hash password avec Argon2ID)
   - Créer OutboxEvent (UserCreated)
   - Sauvegarder User + OutboxEvent (atomique)
   - Commit transaction
   ↓
4. TokenService::createToken()
   - Générer JWT access token (15 min)
   - Générer refresh token (7 jours)
   - Sauvegarder refresh token en base
   ↓
5. Client ← 201 Created
   {
     user: { id, email, name, roles },
     token: { accessToken, refreshToken, expiresIn, tokenType }
   }
   ↓
6. [Arrière-plan] ProcessOutboxEventsCommand (cron toutes les 30s)
   ↓
7. OutboxProcessor::processEvents()
   - Récupérer OutboxEvents PENDING (batch 100)
   - Pour chaque événement :
     * CircuitBreaker.call() → publishEvent()
     * Publier vers RabbitMQ (exchange: events, routing: user.created)
     * Marquer comme PROCESSED
   ↓
8. RabbitMQ → Distribue UserCreatedEvent
   ↓
9. Autres microservices (CRM, ERP) → Consomment l'événement
```

---

### Flux de Connexion (Login)

```
1. Client → POST /api/login { email, password }
   ↓
2. AuthController::login()
   - Vérifier rate limit (5/15min par email)
   - Valider le DTO
   ↓
3. AuthenticationService::authenticate()
   - UserRepository.findByEmail()
   - Vérifier que l'utilisateur existe
   - User.verifyPassword() (Argon2ID verify)
   - Vérifier que l'utilisateur n'est pas supprimé
   ↓
4. TokenService::createToken()
   - Générer JWT access token
   - Générer refresh token
   ↓
5. RateLimiter::clear() (réinitialiser compteur si succès)
   ↓
6. Client ← 200 OK
   {
     accessToken: "eyJhbGc...",
     refreshToken: "def502...",
     expiresIn: 900,
     tokenType: "Bearer"
   }
```

---

### Flux de Rafraîchissement de Token (Token Refresh)

```
1. Client → POST /api/token/refresh { refreshToken }
   ↓
2. AuthController::refresh()
   - Vérifier rate limit (10/15min par IP)
   - Valider le refresh token
   ↓
3. TokenService::refreshToken()
   - Récupérer le refresh token depuis la base
   - Vérifier qu'il est valide et non expiré
   - Récupérer l'utilisateur associé
   - Révoquer l'ancien refresh token
   - Générer nouveau access token
   - Générer nouveau refresh token (rotation)
   ↓
4. Client ← 200 OK
   {
     accessToken: "eyJhbGc...",
     refreshToken: "ghi789...",
     expiresIn: 900,
     tokenType: "Bearer"
   }
```

---

### Flux de Protection Rate Limit

```
1. Requête entrante (login, register, etc.)
   ↓
2. RateLimiter::attempt(key)
   ↓
3. Redis : Récupérer compteur pour la clé
   ↓
4. Si compteur >= maxAttempts (5) :
   - Logger "Rate limit exceeded"
   - Lever RateLimitException
   - Client ← 429 Too Many Requests
     {
       error: "Rate limit exceeded",
       retryAfter: 900
     }
   ↓
5. Sinon :
   - Incrémenter compteur dans Redis
   - Définir expiration (15 minutes)
   - Continuer le traitement
```

---

### Flux de Publication d'Événements (Outbox Processing)

```
1. Cron Job (toutes les 30 secondes)
   ↓
2. ProcessOutboxEventsCommand
   ↓
3. OutboxProcessor::processEvents()
   ↓
4. OutboxRepository::findPendingEvents(100)
   - SELECT * FROM outbox_events
     WHERE status = 'PENDING'
     ORDER BY occurred_at ASC
     LIMIT 100
   ↓
5. Pour chaque OutboxEvent :
   ↓
6. CircuitBreaker::call('rabbitmq', publishEvent)
   ↓
7. Vérifier état du circuit :
   - Si OPEN et timeout non écoulé : lever RuntimeException
   - Si OPEN et timeout écoulé : passer en HALF_OPEN
   - Si CLOSED ou HALF_OPEN : continuer
   ↓
8. publishEvent(event)
   - Créer UserCreatedEvent depuis payload
   - MessageBus::dispatch(event)
   - Symfony Messenger → AMQP Transport → RabbitMQ
   ↓
9. Si succès :
   - CircuitBreaker : réinitialiser compteur échecs
   - OutboxRepository::markAsProcessed(event)
   - UPDATE outbox_events SET status='PROCESSED', processed_at=NOW()
   ↓
10. Si échec :
    - CircuitBreaker : incrémenter compteur échecs
    - Si compteur >= 5 : ouvrir circuit
    - Si event.retryCount < 3 :
      * OutboxRepository::markAsFailed(event, error)
      * UPDATE outbox_events SET status='FAILED', retry_count++
    - Sinon :
      * Logger en CRITICAL (intervention manuelle requise)
```

---

### Flux de Circuit Breaker

```
État CLOSED (Normal)
   │
   │ Appel à RabbitMQ
   ├─ Succès → Reste CLOSED
   │
   └─ Échec → Incrémenter compteur
      │
      └─ Si compteur >= 5 → Passer en OPEN
         │
         │ Timer 60 secondes
         │
         └─ Après 60s → Passer en HALF_OPEN
            │
            │ Prochain appel (test)
            ├─ Succès → Passer en CLOSED (réinitialiser compteur)
            │
            └─ Échec → Repasser en OPEN (nouveau timer 60s)
```

---

## Sécurité

### 1. Hashing des Mots de Passe
- **Algorithme** : Argon2ID (résistant aux attaques GPU)
- **Configuration** :
  - Memory cost : 64 MB
  - Time cost : 4 itérations
  - Threads : 3 parallèles
- **Validation** : Complexité stricte (8 chars, maj, min, chiffre, spécial)

### 2. JWT (JSON Web Tokens)
- **Algorithme** : RS256 (signature asymétrique RSA)
- **Clés** : RSA 4096 bits
- **Access Token** : 15 minutes de validité
- **Refresh Token** : 7 jours, usage unique, rotation

### 3. Rate Limiting
- **Protection** : Brute force, DoS
- **Stockage** : Redis (haute performance)
- **Limites** : Configurables par endpoint

### 4. HTTPS Obligatoire
- Toutes les communications doivent être chiffrées
- Configuration Nginx avec TLS 1.3

### 5. Validation des Entrées
- Validation stricte de tous les DTOs
- Sanitization automatique (trim, lowercase pour emails)
- Protection contre injection SQL (Doctrine ORM)

### 6. Logging de Sécurité
- Toutes les tentatives d'authentification (succès/échecs)
- Dépassements de rate limit
- Erreurs d'accès
- Corrélation IDs pour traçabilité

---

## Performance et Scalabilité

### 1. Base de Données
- **Index** : Sur email, createdAt, status
- **Connection Pooling** : Doctrine DBAL
- **Requêtes optimisées** : Batch processing, pagination

### 2. Cache
- **Redis** : Rate limiting, circuit breaker state
- **Doctrine Cache** : Second-level cache pour entités

### 3. Messaging
- **RabbitMQ** : Communication asynchrone
- **Batch Processing** : Outbox events par 100
- **Circuit Breaker** : Protection contre surcharge

### 4. Scalabilité Horizontale
- **Stateless** : JWT permet scaling horizontal
- **Shared Nothing** : Chaque instance indépendante
- **Load Balancing** : Nginx upstream

### 5. Monitoring
- **Health Checks** : Liveness, Readiness
- **Metrics** : Prometheus-ready
- **Logging** : JSON structured logs
- **Tracing** : Correlation IDs

---

## Déploiement

### 1. Docker
```yaml
Services :
- php-fpm : Application Symfony
- nginx : Reverse proxy
- postgres : Base de données
- rabbitmq : Message broker
- redis : Cache
```

### 2. Kubernetes (Recommandé Production)
```yaml
Deployments :
- auth-api : 3 replicas (scalable)
- outbox-processor : 1 replica (CronJob)

Services :
- auth-api-service : LoadBalancer
- postgres-service : StatefulSet
- rabbitmq-service : StatefulSet
- redis-service : StatefulSet
```

### 3. CI/CD
```yaml
Pipeline :
1. Tests (Unit, Integration, Functional)
2. Code Quality (PHPStan, PHP CS Fixer)
3. Build Docker Image
4. Push to Registry
5. Deploy to Kubernetes
6. Health Check
7. Rollback si échec
```

---

## Tests

### 1. Tests Unitaires
- **Cible** : Value Objects, Entities, Services
- **Framework** : PHPUnit
- **Couverture** : > 80%

### 2. Tests d'Intégration
- **Cible** : Repositories, Messaging
- **Base de données** : PostgreSQL test
- **Fixtures** : Données de test

### 3. Tests Fonctionnels
- **Cible** : Endpoints API
- **Framework** : Symfony WebTestCase
- **Scénarios** : End-to-end complets

### 4. Commandes
```bash
# Tous les tests
make test

# Tests unitaires uniquement
make test-unit

# Tests d'intégration
make test-integration

# Couverture de code
make coverage
```

---

## Maintenance

### 1. Logs
```
Emplacements :
- var/log/dev.log : Développement
- var/log/prod.log : Production
- var/log/error.log : Erreurs uniquement
```

### 2. Monitoring
```
Endpoints :
- /api/health : Health check basique
- /api/health/ready : Readiness probe
- /api/health/live : Liveness probe
```

### 3. Backup
```
À sauvegarder :
- Base de données PostgreSQL
- Clés JWT (var/jwt/)
- Configuration (.env)
```

### 4. Rotation des Clés JWT
```bash
# Générer nouvelles clés
php bin/console app:generate-jwt-keys

# Redémarrer les services
docker-compose restart
```

---

## Conclusion

Ce microservice d'authentification est conçu selon les meilleures pratiques :

✅ **Architecture Propre** : Séparation claire des responsabilités
✅ **SOLID** : Principes respectés dans tout le code
✅ **Sécurité** : Argon2ID, JWT, Rate Limiting, Circuit Breaker
✅ **Résilience** : Outbox Pattern, Circuit Breaker, Retry
✅ **Scalabilité** : Stateless, Horizontal scaling, Async messaging
✅ **Testabilité** : Interfaces, Dependency Injection, Mocking
✅ **Observabilité** : Logging structuré, Health checks, Metrics
✅ **Production-Ready** : Docker, Kubernetes, CI/CD

Le code est maintenable, évolutif et prêt pour la production.