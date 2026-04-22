<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console\Commands;

use Fnlla\Console\CommandInterface;
use Fnlla\Console\ConsoleIO;

final class MakeBlueprintCommand extends AbstractMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:blueprint';
    }

    public function getDescription(): string
    {
        return 'Scaffold domain blueprints (crm, school, crm-school).';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $name = strtolower(trim((string) ($args[0] ?? '')));
        if ($name === '') {
            $io->error('Blueprint name is required.');
            $this->printAvailable($io);
            return 1;
        }

        $definitions = $this->definitions();
        if (!isset($definitions[$name])) {
            $io->error('Unknown blueprint: ' . $name);
            $this->printAvailable($io);
            return 1;
        }

        $definition = $definitions[$name];
        $entities = $definition['entities'] ?? [];
        $modules = $definition['modules'] ?? [];

        $planOnly = isset($options['plan']) || isset($options['dry']);
        $withModules = isset($options['module']) || isset($options['modules']);
        $skipExisting = isset($options['skip-existing']) || isset($options['skip_existing']);

        $plan = [];
        if ($withModules && $modules !== []) {
            foreach ($modules as $module) {
                $plan[] = ['make:module', $module];
            }
        }
        foreach ($entities as $entity) {
            $plan[] = ['make:crud', $entity];
        }

        if ($planOnly) {
            $io->line('Blueprint plan (' . $name . '):');
            foreach ($plan as $step) {
                $io->line(' - ' . $step[0] . ' ' . $step[1]);
            }
            return 0;
        }

        if ($withModules && $modules !== []) {
            $moduleCommand = new MakeModuleCommand();
            foreach ($modules as $module) {
                $status = $moduleCommand->run([$module], [], $io, $root);
                if ($status !== 0) {
                    return $status;
                }
            }
        }

        $crudCommand = new MakeCrudCommand();
        foreach ($entities as $entity) {
            if ($skipExisting && $this->modelExists($root, $entity)) {
                $io->warn('Skipping existing model: ' . $this->studly($entity));
                continue;
            }
            $status = $crudCommand->run([$entity], [], $io, $root);
            if ($status !== 0) {
                return $status;
            }
        }

        $io->line('Blueprint scaffold complete.');
        return 0;
    }

    /**
     * @return array<string, array{entities: string[], modules?: string[]}>
     */
    private function definitions(): array
    {
        return [
            'crm' => [
                'modules' => ['Crm'],
                'entities' => [
                    'Customer',
                    'Company',
                    'Contact',
                    'Deal',
                    'PipelineStage',
                    'Activity',
                    'Note',
                ],
            ],
            'school' => [
                'modules' => ['School'],
                'entities' => [
                    'School',
                    'Student',
                    'Guardian',
                    'Teacher',
                    'Classroom',
                    'Enrollment',
                    'Attendance',
                    'Grade',
                    'Term',
                ],
            ],
            'crm-school' => [
                'modules' => ['Crm', 'School'],
                'entities' => [
                    'Customer',
                    'Company',
                    'Contact',
                    'Deal',
                    'PipelineStage',
                    'Activity',
                    'Note',
                    'School',
                    'Student',
                    'Guardian',
                    'Teacher',
                    'Classroom',
                    'Enrollment',
                    'Attendance',
                    'Grade',
                    'Term',
                ],
            ],
            'saas' => [
                'modules' => ['SaaS'],
                'entities' => [
                    'Account',
                    'TeamMember',
                    'Invitation',
                    'Plan',
                    'Subscription',
                    'Invoice',
                    'Payment',
                    'UsageMetric',
                    'FeatureFlag',
                    'ApiToken',
                ],
            ],
            'commerce' => [
                'modules' => ['Commerce'],
                'entities' => [
                    'Product',
                    'Category',
                    'Inventory',
                    'Cart',
                    'CartItem',
                    'Order',
                    'OrderItem',
                    'Checkout',
                    'Shipment',
                    'Payment',
                    'Coupon',
                ],
            ],
            'marketplace' => [
                'modules' => ['Marketplace'],
                'entities' => [
                    'Vendor',
                    'Listing',
                    'Order',
                    'OrderItem',
                    'Payout',
                    'Review',
                ],
            ],
            'erp' => [
                'modules' => ['Erp'],
                'entities' => [
                    'Organization',
                    'Department',
                    'Employee',
                    'Vendor',
                    'PurchaseOrder',
                    'PurchaseOrderItem',
                    'InventoryItem',
                    'Invoice',
                    'Payment',
                    'LedgerEntry',
                    'Asset',
                ],
            ],
            'healthcare' => [
                'modules' => ['Healthcare'],
                'entities' => [
                    'Patient',
                    'Practitioner',
                    'Appointment',
                    'Encounter',
                    'Prescription',
                    'Medication',
                    'LabOrder',
                    'LabResult',
                    'InsurancePolicy',
                    'Claim',
                    'Clinic',
                ],
            ],
            'real-estate' => [
                'modules' => ['RealEstate'],
                'entities' => [
                    'Property',
                    'Listing',
                    'Unit',
                    'Owner',
                    'Tenant',
                    'Lease',
                    'MaintenanceRequest',
                    'Inspection',
                    'Application',
                    'Payment',
                ],
            ],
            'logistics' => [
                'modules' => ['Logistics'],
                'entities' => [
                    'Shipment',
                    'Route',
                    'Stop',
                    'Driver',
                    'Vehicle',
                    'Carrier',
                    'Warehouse',
                    'InventoryItem',
                    'TrackingEvent',
                    'Delivery',
                ],
            ],
        ];
    }

    private function printAvailable(ConsoleIO $io): void
    {
        $io->line('Available blueprints: crm, school, crm-school, saas, commerce, marketplace, erp, healthcare, real-estate, logistics');
        $io->line('Tip: add --plan to preview and --skip-existing to avoid conflicts.');
    }

    private function modelExists(string $root, string $name): bool
    {
        $model = $this->studly($name);
        $path = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . $model . '.php';
        return is_file($path);
    }
}
