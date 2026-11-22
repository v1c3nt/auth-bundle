<?php

namespace Vvbx\SuiteBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vvbx\SuiteBundle\Account\AccountException;
use Vvbx\SuiteBundle\Account\AccountManagerInterface;

// ---- appelée par le provisioning de la suite : relançable, sans mot de passe ni e-mail envoyé
#[AsCommand(name: 'vvbx:user:preauthorize', description: 'Pré-autorise un compte par e-mail pour la connexion SSO')]
final class PreauthorizeUserCommand extends Command
{
    public function __construct(private readonly AccountManagerInterface $accounts)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, "E-mail du compte, tel qu'il est vérifié par l'IdP")
            ->addArgument('role', InputArgument::REQUIRED, sprintf('Rôle (%s)', implode(', ', $this->accounts->roles())));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $role = (string) $input->getArgument('role');

        if (!\in_array($role, $this->accounts->roles(), true)) {
            $io->error(sprintf('Rôle inconnu : "%s".', $role));

            return Command::FAILURE;
        }

        try {
            $created = $this->accounts->preauthorize($email, $role);
        } catch (AccountException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success($created
            ? sprintf('Compte "%s" pré-autorisé (%s).', $email, $role)
            : sprintf('Compte "%s" déjà présent, rôle %s assuré.', $email, $role));

        return Command::SUCCESS;
    }
}
