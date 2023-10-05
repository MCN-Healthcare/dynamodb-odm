<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Console\Commands;

use McnHealthcare\ODM\Dynamodb\Helpers\DynamoDbManager;
use McnHealthcare\ODM\Dynamodb\Exceptions\ODMException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreateSchemaCommand
 * Console command to create dynamodb schema.
 */
class CreateSchemaCommand extends AbstractSchemaCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('odm:schema-tool:create')
             ->setDescription('Processes the schema and create corresponding tables and indices.')
             ->addOption('skip-existing-table', null, InputOption::VALUE_NONE, "skip creating existing table!")
             ->addOption(
                 'dry-run',
                 null,
                 InputOption::VALUE_NONE,
                 "output possible table creations without actually creating them."
             );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $skipExisting = $input->getOption('skip-existing-table');
        $dryRun = $input->getOption('dry-run');
        $im = $this->getItemManager();
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamoDbClient());

        $classes = $this->getManagedItemClasses();
        foreach ($classes as $class => $reflection) {
            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();
            if ($dynamoManager->listTables(sprintf("/^%s\$/", preg_quote($tableName, "/")))) {
                if ( ! $skipExisting && ! $dryRun) {
                    throw new ODMException("Table " . $tableName . " already exists!");
                }
            }
        }

        $waits = [];
        foreach ($classes as $class => $reflection) {
            $itemDef = $reflection->getItemDefinition();
            if ($itemDef->projected) {
                $output->writeln(
                    "Will not create projected table <info>$class</info>"
                );
                $this->logger->notice(
                    sprintf("Class %s is projected class, will not create table.", $class)
                );
                continue;
            }

            $attributeTypes = $reflection->getAttributeTypes();
            $fieldNameMapping = $reflection->getFieldNameMapping();

            $lsis = [];
            foreach ($itemDef->localSecondaryIndices as $localSecondaryIndex) {
                $lsis[] = $localSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);
            }
            $gsis = [];
            foreach ($itemDef->globalSecondaryIndices as $globalSecondaryIndex) {
                $gsis[] = $globalSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);
            }

            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();

            $output->writeln("Will create table <info>$tableName</info> for class <info>$class</info> ...");
            if ( ! $dryRun) {
                $dynamoManager->createTable(
                    $tableName,
                    $itemDef->primaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes),
                    $lsis,
                    $gsis
                );

                if ($gsis) {
                    // if there is gsi, we nee to wait before creating next table
                    $output->writeln("Will wait for GSI creation ...");
                    $dynamoManager->waitForTablesToBeFullyReady($tableName, 60, 2);
                } else {
                    $waits[] = $dynamoManager->waitForTableCreation(
                        $tableName,
                        60,
                        1,
                        false
                    );
                }
                $output->writeln('Created.');
            }
        }

        if ( ! $dryRun) {
            $output->writeln("Waiting for all tables to be active ...");
            \GuzzleHttp\Promise\all($waits)->wait();
            $output->writeln("Done.");
        }

        return static::SUCCESS;
    }

}
