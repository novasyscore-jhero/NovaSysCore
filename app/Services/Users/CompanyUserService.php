<?php

namespace App\Services\Users;

use NovaSysCore\Database;
use PDO;
use RuntimeException;
use Throwable;
use App\Exceptions\Users\MembershipExistsException;
use App\Exceptions\Users\MembershipInactiveException;
use App\Exceptions\Users\UserInactiveException;

class CompanyUserService
{
    private PDO $pdo;

    public function __construct(
        ?PDO $pdo = null
    ) {
        $this->pdo =
            $pdo ?? Database::connection();
    }

    public function createOrAttach(
        int $companyId,
        string $name,
        string $lastName,
        string $displayName,
        string $email,
        string $phone,
        string $password
    ): int {
        $email = mb_strtolower(
            trim($email)
        );

        try {
            $this->pdo->beginTransaction();

            $statement = $this->pdo->prepare("
                SELECT
                    id,
                    status
                FROM users
                WHERE email = :email
                LIMIT 1
                FOR UPDATE
            ");

            $statement->execute([
                ':email' => $email,
            ]);

            $existingUser =
                $statement->fetch();

            if (!$existingUser) {
                $userId = $this->createIdentity(
                    $name,
                    $lastName,
                    $displayName,
                    $email,
                    $phone,
                    $password
                );
            } else {
                $userId =
                    (int) $existingUser['id'];

                if (
                    ($existingUser['status'] ?? null)
                    !== 'active'
                ) {
                    throw new UserInactiveException();
                }
            }

            $statement = $this->pdo->prepare("
                SELECT
                    id,
                    status
                FROM user_companies
                WHERE user_id = :user_id
                  AND company_id = :company_id
                LIMIT 1
                FOR UPDATE
            ");

            $statement->execute([
                ':user_id' => $userId,
                ':company_id' => $companyId,
            ]);

            $membership =
                $statement->fetch();

            if ($membership) {
                if (
                    ($membership['status'] ?? null)
                    === 'active'
                ) {
                    throw new MembershipExistsException();
                }

                throw new MembershipInactiveException();
            }

            $statement = $this->pdo->prepare("
                INSERT INTO user_companies (
                    user_id,
                    company_id,
                    status
                )
                VALUES (
                    :user_id,
                    :company_id,
                    'active'
                )
            ");

            $statement->execute([
                ':user_id' => $userId,
                ':company_id' => $companyId,
            ]);

            $this->pdo->commit();

            return $userId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function createIdentity(
        string $name,
        string $lastName,
        string $displayName,
        string $email,
        string $phone,
        string $password
    ): int {
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            throw new RuntimeException(
                'PASSWORD_HASH_FAILED'
            );
        }

        $statement = $this->pdo->prepare("
            INSERT INTO users (
                name,
                last_name,
                display_name,
                email,
                password_hash,
                phone,
                status
            )
            VALUES (
                :name,
                :last_name,
                :display_name,
                :email,
                :password_hash,
                :phone,
                'active'
            )
        ");

        $statement->execute([
            ':name' => $name,
            ':last_name' =>
                $lastName !== ''
                    ? $lastName
                    : null,
            ':display_name' =>
                $displayName !== ''
                    ? $displayName
                    : null,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':phone' =>
                $phone !== ''
                    ? $phone
                    : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}