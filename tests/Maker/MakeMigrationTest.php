<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Tests\Maker;

use Symfony\Bundle\MakerBundle\Maker\MakeMigration;
use Symfony\Bundle\MakerBundle\Test\MakerTestCase;
use Symfony\Bundle\MakerBundle\Test\MakerTestDetails;
use Symfony\Bundle\MakerBundle\Test\MakerTestRunner;
use Symfony\Component\Finder\Finder;

class MakeMigrationTest extends MakerTestCase
{
    protected function getMakerClass(): string
    {
        return MakeMigration::class;
    }

    private function createMakeMigrationTest(): MakerTestDetails
    {
        return $this->createMakerTest()
            // doctrine-migrations-bundle only requires doctrine-bundle, which
            // only requires doctrine/dbal. But we're testing with the ORM,
            // so let's install it
            ->addExtraDependencies('doctrine/orm:@stable')
            ->preRun(function (MakerTestRunner $runner) {
                $runner->copy(
                    'make-migration/SpicyFood.php',
                    'src/Entity/SpicyFood.php'
                );

                $runner->configureDatabase(false);
            })
        ;
    }

    public function getTestDetails()
    {
        yield 'it_generates_migration_with_changes' => [$this->createMakeMigrationTest()
            ->run(function (MakerTestRunner $runner) {
                $output = $runner->runMaker([/* no input */]);

                $this->assertStringContainsString('Success', $output);

                // support for Migrations 3 (/migrations) and earlier
                $migrationsDirectoryPath = file_exists($runner->getPath('/migrations')) ? 'migrations' : 'src/Migrations';

                $finder = new Finder();
                $finder->in($runner->getPath($migrationsDirectoryPath))
                    ->name('*.php');
                $this->assertCount(1, $finder);

                // see that the exact filename is in the output
                $iterator = $finder->getIterator();
                $iterator->rewind();
                $this->assertStringContainsString(sprintf('"%s/%s"', $migrationsDirectoryPath, $iterator->current()->getFilename()), $output);
            }),
        ];

        yield 'it_generates_migration_with_no_changes' => [$this->createMakeMigrationTest()
            ->run(function (MakerTestRunner $runner) {
                // sync so there are no changes
                $runner->updateSchema();
                $output = $runner->runMaker([/* no input */]);

                $this->assertStringNotContainsString('Success', $output);

                $this->assertStringContainsString('No database changes were detected', $output);
            }),
        ];

        yield 'it_asks_previous_migration_question' => [$this->createMakeMigrationTest()
            ->addRequiredPackageVersion('doctrine/doctrine-migrations-bundle', '>=3')
            ->run(function (MakerTestRunner $runner) {
                // generate a migration first
                $runner->runConsole('make:migration', []);

                $output = $runner->runMaker([
                    // confirm migration
                    'y',
                ]);

                $this->assertStringContainsString('You have 1 available migrations to execute', $output);
                $this->assertStringContainsString('Success', $output);
                $this->assertCount(14, explode("\n", $output), 'Asserting that very specific output is shown - some should be hidden');
            }),
        ];

        yield 'it_asks_previous_migration_question_and_decline' => [$this->createMakeMigrationTest()
            ->addRequiredPackageVersion('doctrine/doctrine-migrations-bundle', '>=3')
            ->run(function (MakerTestRunner $runner) {
                // generate a migration first
                $runner->runConsole('make:migration', []);

                $output = $runner->runMaker([
                    // no to confirm
                    'n',
                ]);

                $this->assertStringNotContainsString('Success', $output);
            }),
        ];
    }
}
