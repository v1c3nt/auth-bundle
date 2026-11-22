<?php

namespace Vvbx\SuiteBundle\Account;

/**
 * Gestion des comptes d'une app, implémentée par l'app (elle reste maîtresse de ses rôles et de leurs règles).
 *
 * Utilisée par la commande de pré-autorisation appelée au provisioning, puis par l'API du portail client.
 */
interface AccountManagerInterface
{
    /**
     * Rôles attribuables par la suite (ex. ROLE_ADMIN, ROLE_MANAGER).
     *
     * @return list<string>
     */
    public function roles(): array;

    /**
     * Pré-autorise un compte par e-mail, sans mot de passe ni e-mail envoyé ; relançable.
     * Un compte existant reçoit le rôle s'il ne l'a pas déjà. Il sera rattaché au sub à sa première connexion SSO.
     *
     * @return bool true si le compte a été créé, false s'il existait déjà
     *
     * @throws AccountException règle de l'app non respectée (ex. nombre maximal d'administrateurs)
     */
    public function preauthorize(string $email, string $role): bool;
}
