# Forgot Password Feature Implementation Guide

## Overview
This document provides comprehensive information about the forgot password feature implementation in the authentication microservice.

## Architecture

### Components
1. **Domain Layer**
   - `PasswordResetToken` - Value object for secure token generation
   - `User` entity - Extended with reset token fields and methods

2. **Application Layer**
   - `ForgotPasswordDTO` - Input validation for forgot password requests
   - `ResetPasswordDTO` - Input validation for password reset
   - `PasswordResetService` - Business logic and email sending

3. **Infrastructure Layer**
   - `UserRepository` - Extended with `findByResetToken` method

4. **Presentation Layer**
   - `PasswordResetController` - REST API endpoints

## Database Schema

### Migration
Run the migration to add reset token fields:
```bash
docker-compose exec php bin/console doctrine:migrations:migrate
```

### New Fields in `users` table
- `reset_token` (VARCHAR 255, nullable, indexed)
- `reset_token_expires_at` (TIMESTAMP, nullable)

## API Endpoints

### 1. Request Password Reset
**Endpoint:** `POST /api/password/forgot`

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Response (200 OK):**
```json
{
  "message": "If an account exists with this email, a password reset link has been sent."
}
```

**Note:** Always returns success to prevent email enumeration attacks.

### 2. Reset Password
**Endpoint:** `POST /api/password/reset`

**Request Body:**
```json
{
  "token": "reset-token-from-email",
  "password": "NewSecureP@ssw0rd",
  "passwordConfirmation": "NewSecureP@ssw0rd"
}
```

**Response (200 OK):**
```json
{
  "message": "Password has been successfully reset"
}
```

**Response (400 Bad Request):**
```json
{
  "error": "Invalid or expired reset token"
}
```

### 3. Verify Reset Token
**Endpoint:** `GET /api/password/reset/verify/{token}`

**Response (200 OK):**
```json
{
  "valid": true
}
```

## Security Features

### 1. Rate Limiting
- Maximum 3 password reset requests per IP address
- 15-minute sliding window
- Configured in `config/packages/rate_limiter.yaml`

### 2. Token Security
- 64-character random token (256 bits of entropy)
- 1-hour expiration time
- Secure token comparison using `hash_equals()`
- Single-use tokens (cleared after successful reset)

### 3. Email Enumeration Prevention
- Always returns success message regardless of email existence
- Logs attempts for security monitoring

### 4. Password Requirements
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (@$!%*?&)

## Email Configuration

### Environment Variables
Add to `.env`:
```env
MAILER_DSN=smtp://localhost:1025
APP_URL=http://localhost:8080
```

### Production Configuration
For production, use a real SMTP service:
```env
MAILER_DSN=smtp://username:password@smtp.example.com:587
APP_URL=https://yourdomain.com
```

### Email Template
Located at `templates/emails/password_reset.html.twig`
- Professional HTML design
- Responsive layout
- Clear call-to-action button
- Security warnings
- Expiration information

## Testing

### 1. Using Docker
```bash
# Start services
docker-compose up -d

# Run migration
docker-compose exec php bin/console doctrine:migrations:migrate

# Test forgot password endpoint
curl -X POST http://localhost:8080/api/password/forgot \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com"}'

# Check email (if using MailHog)
# Visit http://localhost:8025
```

### 2. Testing Flow
1. Request password reset with valid email
2. Check email inbox (or MailHog if configured)
3. Extract reset token from email link
4. Verify token validity
5. Submit new password with token
6. Confirm password has been changed

### 3. MailHog Setup (Development)
Add to `docker-compose.yml`:
```yaml
mailhog:
  image: mailhog/mailhog
  ports:
    - "1025:1025"  # SMTP
    - "8025:8025"  # Web UI
```

Update `.env`:
```env
MAILER_DSN=smtp://mailhog:1025
```

## Error Handling

### Common Errors

1. **Invalid Email Format**
   - Status: 400 Bad Request
   - Response: Validation error message

2. **Invalid or Expired Token**
   - Status: 400 Bad Request
   - Response: "Invalid or expired reset token"

3. **Password Validation Failed**
   - Status: 400 Bad Request
   - Response: Specific validation error messages

4. **Rate Limit Exceeded**
   - Status: 200 OK (to prevent enumeration)
   - Action: Request silently ignored, logged

5. **Email Sending Failed**
   - Status: 500 Internal Server Error
   - Action: Logged, user notified of generic error

## Monitoring and Logging

### Log Events
- Password reset requests (with email)
- Email sending success/failure
- Rate limit violations
- Invalid token attempts
- Successful password resets

### Log Locations
- Application logs: `var/log/prod.log` or `var/log/dev.log`
- Check logs with: `docker-compose logs -f php`

## Frontend Integration

### Example JavaScript Implementation
```javascript
// Request password reset
async function forgotPassword(email) {
  const response = await fetch('http://localhost:8080/api/password/forgot', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  });
  return await response.json();
}

// Verify token
async function verifyResetToken(token) {
  const response = await fetch(`http://localhost:8080/api/password/reset/verify/${token}`);
  return await response.json();
}

// Reset password
async function resetPassword(token, password, passwordConfirmation) {
  const response = await fetch('http://localhost:8080/api/password/reset', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token, password, passwordConfirmation })
  });
  return await response.json();
}
```

## Maintenance

### Token Cleanup
Consider adding a scheduled task to clean expired tokens:
```php
// src/Command/CleanExpiredTokensCommand.php
// Remove tokens older than 24 hours
```

### Security Audits
- Regularly review rate limiting effectiveness
- Monitor for suspicious patterns
- Update password requirements as needed
- Review email delivery success rates

## Troubleshooting

### Email Not Sending
1. Check MAILER_DSN configuration
2. Verify SMTP server connectivity
3. Check application logs for errors
4. Test with MailHog in development

### Token Not Working
1. Verify token hasn't expired (1 hour limit)
2. Check token hasn't been used already
3. Ensure token matches exactly (no extra spaces)
4. Verify database migration ran successfully

### Rate Limiting Issues
1. Check Redis connectivity
2. Verify rate limiter configuration
3. Review IP address detection
4. Consider adjusting limits for production

## Best Practices

1. **Always use HTTPS in production** for reset links
2. **Configure proper email sender** (not noreply@example.com)
3. **Monitor rate limiting** and adjust as needed
4. **Implement additional security** (2FA, security questions) for sensitive applications
5. **Regular security audits** of password reset flow
6. **User notification** when password is changed
7. **Log all security events** for audit trail

## Support

For issues or questions:
1. Check application logs
2. Review this documentation
3. Contact development team
4. Submit issue to project repository