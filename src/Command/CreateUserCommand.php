<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Commande interactive pour créer un utilisateur en base de données avec un rôle prédéfini.
 * Utilisée en ligne de commande pour initialiser des comptes administrateurs ou de test.
 */
#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un utilisateur en base avec un rôle prédéfini.',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface      $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Création d\'un utilisateur');

        // ── Email ────────────────────────────────────────────────────────────
        $email = $io->ask('Email', null, function (?string $value): string {
            if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Email invalide.');
            }
            return $value;
        });

        // Vérifier unicité
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $io->error("Un utilisateur avec l'email \"$email\" existe déjà.");
            return Command::FAILURE;
        }

        // ── Mot de passe ─────────────────────────────────────────────────────
        $plainPassword = $io->askHidden('Mot de passe (masqué)', function (?string $value): string {
            if (empty($value) || strlen($value) < 8) {
                throw new \RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
            }
            return $value;
        });

        $confirm = $io->askHidden('Confirmer le mot de passe', function (?string $value): string {
            return $value ?? '';
        });

        if ($plainPassword !== $confirm) {
            $io->error('Les mots de passe ne correspondent pas.');
            return Command::FAILURE;
        }

        // ── Rôle ─────────────────────────────────────────────────────────────
        $roleChoices = [];
        foreach (UserRole::cases() as $role) {
            $roleChoices[$role->label()] = $role;
        }

        $roleLabel = $io->choice(
            'Rôle',
            array_keys($roleChoices),
            UserRole::User->label(),
        );

        $selectedRole = $roleChoices[$roleLabel];

        // ── Nom complet (optionnel) ───────────────────────────────────────────
        $fullName = $io->ask('Nom complet (optionnel)', null);

        // ── Création ─────────────────────────────────────────────────────────
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($selectedRole->impliedRoles());
        $user->setIsVerified(true);

        if ($fullName) {
            $user->setFullName($fullName);
        }

        $hashed = $this->hasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf(
            'Utilisateur créé avec succès : %s (%s)',
            $email,
            $selectedRole->label(),
        ));

        return Command::SUCCESS;
    }
}
