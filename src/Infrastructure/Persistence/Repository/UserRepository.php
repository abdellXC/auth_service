<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\User;
use App\Domain\Repository\IUserRepository;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository implements IUserRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findById(UserId $id): ?User
    {
        return $this->find($id->getValue());
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->findOneBy(['email' => $email->getValue()]);
    }

    public function findByResetToken(string $token): ?User
    {
        return $this->findOneBy(['resetToken' => $token]);
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->count(['email' => $email->getValue()]) > 0;
    }

    public function delete(User $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }
}