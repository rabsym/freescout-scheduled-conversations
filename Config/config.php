<?php

/**
 * Scheduled Conversations Module Configuration
 *
 * Default configuration values for the module.
 * These values can be overridden via the Settings page (Administrar > Configuración)
 * where they are stored in the FreeScout options table.
 *
 * process_frequency: How often (in minutes) the scheduler runs the processing command.
 *                    Supported values: 1, 5, 15. Default: 5 (recommended for production).
 *                    Can be changed at runtime via the module Settings page.
 *
 * max_per_cycle: Maximum number of scheduled conversations processed per scheduler run.
 *               Prevents timeouts on large installations.
 *
 * @package Modules\ScheduledConversations
 * @author  Raimundo Alba
 */

return [
    'name' => 'ScheduledConversations',
    
    'process_frequency' => 5, // Default: 5 minutes. Can be changed via Settings page.
    'max_per_cycle' => 100,
    'enable_catchup' => true,
    
    'status' => [
        'active' => 'active',
        'paused' => 'paused',
        'expired' => 'expired',
    ],
    
    'destination_types' => [
        'internal' => 'internal',
        'customer' => 'customer',
        'email' => 'email',
    ],
    
    'frequency_types' => [
        'once' => 'once',
        'daily' => 'daily',
        'weekly' => 'weekly',
        'monthly' => 'monthly',
        'monthly_ordinal' => 'monthly_ordinal',
        'yearly' => 'yearly',
    ],
    
    'variables' => [
        '{customer_name}',
        '{date}',
        '{time}',
        '{mailbox_name}',
        '{user_name}',
    ],
];
