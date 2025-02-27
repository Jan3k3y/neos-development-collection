<?php

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use PDO;

/**
 * Set the Workspace "owner" field for all personal workspaces with special characters in the username
 */
class Version20151223125946 extends AbstractMigration
{
    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform));

        $schemaManager = $this->connection->createSchemaManager();
        $hasTables = $schemaManager->tablesExist(['typo3_typo3cr_domain_model_workspace']);
        if ($hasTables) {
            $workspacesWithoutOwnerQuery
                = $this->connection->executeQuery('SELECT name FROM typo3_typo3cr_domain_model_workspace t0 WHERE t0.name LIKE \'user-%\' AND t0.owner IS NULL');
            $workspacesWithoutOwner = $workspacesWithoutOwnerQuery->fetchAll(PDO::FETCH_ASSOC);
            if ($workspacesWithoutOwner === []) {
                return;
            }

            $neosAccountQuery
                = $this->connection->executeQuery('SELECT t0.party_abstractparty, t1.accountidentifier FROM typo3_party_domain_model_abstractparty_accounts_join t0 JOIN typo3_flow_security_account t1 ON t0.flow_security_account = t1.persistence_object_identifier WHERE t1.authenticationprovidername = \'Typo3BackendProvider\'');
            while ($account = $neosAccountQuery->fetch(PDO::FETCH_ASSOC)) {
                $normalizedUsername = preg_replace('/[^a-z0-9]/i', '', $account['accountidentifier']) ?: '';

                foreach ($workspacesWithoutOwner as $workspaceWithoutOwner) {
                    if ($workspaceWithoutOwner['name'] === 'user-' . $normalizedUsername) {
                        $this->addSql('UPDATE typo3_typo3cr_domain_model_workspace SET owner = \''
                            . $account['party_abstractparty'] . '\' WHERE name = \'user-' . $normalizedUsername . '\'');
                        continue 2;
                    }
                }
            }
        }
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform));
        $schemaManager = $this->connection->createSchemaManager();
        $hasTables = $schemaManager->tablesExist(['typo3_typo3cr_domain_model_workspace']);
        if ($hasTables) {
            $this->addSql('UPDATE typo3_typo3cr_domain_model_workspace SET owner = NULL');
        }
    }
}
