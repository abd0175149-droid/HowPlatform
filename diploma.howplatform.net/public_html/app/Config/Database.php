<?php

namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    public string $filesPath    = APPPATH . 'Database' . DIRECTORY_SEPARATOR;
    public string $defaultGroup = 'default';

    /** نبقيها من .env */
    public array $default = [];
    public array $remote  = [];

    /** [NEW] مجموعات إضافية */
    public array $frontend = [];
    public array $tools    = [];
    public array $library  = [];

    public array $tests = [
        // ... كما هي
    ];

    public function __construct()
    {
        parent::__construct();

        // -------- default --------
        $this->default = [
            'DSN'      => '',
            'hostname' => getenv('database.default.hostname') ?: 'localhost',
            'username' => getenv('database.default.username') ?: '',
            'password' => getenv('database.default.password') ?: '',
            'database' => getenv('database.default.database') ?: '',
            'DBDriver' => getenv('database.default.DBDriver') ?: 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => env('CI_ENVIRONMENT') !== 'production',
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_unicode_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'port'     => (int) getenv('database.default.port') ?: 3306,
            'numberNative' => false,
            'foundRows'    => false,
            'dateFormat'   => [
                'date'     => 'Y-m-d',
                'datetime' => 'Y-m-d H:i:s',
                'time'     => 'H:i:s',
            ],
        ];

        // -------- remote (auth) --------
        $this->remote = [
            'DSN'      => '',
            'hostname' => getenv('database.remote.hostname') ?: 'localhost',
            'username' => getenv('database.remote.username') ?: '',
            'password' => getenv('database.remote.password') ?: '',
            'database' => getenv('database.remote.database') ?: '',
            'DBDriver' => getenv('database.remote.DBDriver') ?: 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => env('CI_ENVIRONMENT') !== 'production',
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_unicode_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'port'     => (int) getenv('database.remote.port') ?: 3306,
            'numberNative' => false,
            'foundRows'    => false,
            'dateFormat'   => [
                'date'     => 'Y-m-d',
                'datetime' => 'Y-m-d H:i:s',
                'time'     => 'H:i:s',
            ],
        ];

        // -------- [NEW] frontend --------
        $this->frontend = [
            'DSN'      => '',
            'hostname' => getenv('database.frontend.hostname') ?: 'localhost',
            'username' => getenv('database.frontend.username') ?: '',
            'password' => getenv('database.frontend.password') ?: '',
            'database' => getenv('database.frontend.database') ?: '',
            'DBDriver' => getenv('database.frontend.DBDriver') ?: 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => env('CI_ENVIRONMENT') !== 'production',
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_unicode_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'port'     => (int) getenv('database.frontend.port') ?: 3306,
            'numberNative' => false,
            'foundRows'    => false,
            'dateFormat'   => [
                'date'     => 'Y-m-d',
                'datetime' => 'Y-m-d H:i:s',
                'time'     => 'H:i:s',
            ],
        ];

        // -------- [NEW] tools --------
        $this->tools = [
            'DSN'      => '',
            'hostname' => getenv('database.tools.hostname') ?: 'localhost',
            'username' => getenv('database.tools.username') ?: '',
            'password' => getenv('database.tools.password') ?: '',
            'database' => getenv('database.tools.database') ?: '',
            'DBDriver' => getenv('database.tools.DBDriver') ?: 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => env('CI_ENVIRONMENT') !== 'production',
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_unicode_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'port'     => (int) getenv('database.tools.port') ?: 3306,
            'numberNative' => false,
            'foundRows'    => false,
            'dateFormat'   => [
                'date'     => 'Y-m-d',
                'datetime' => 'Y-m-d H:i:s',
                'time'     => 'H:i:s',
            ],
        ];

        // -------- [NEW] library --------
        $this->library = [
            'DSN'      => '',
            'hostname' => getenv('database.library.hostname') ?: 'localhost',
            'username' => getenv('database.library.username') ?: '',
            'password' => getenv('database.library.password') ?: '',
            'database' => getenv('database.library.database') ?: '',
            'DBDriver' => getenv('database.library.DBDriver') ?: 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => env('CI_ENVIRONMENT') !== 'production',
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_unicode_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'port'     => (int) getenv('database.library.port') ?: 3306,
            'numberNative' => false,
            'foundRows'    => false,
            'dateFormat'   => [
                'date'     => 'Y-m-d',
                'datetime' => 'Y-m-d H:i:s',
                'time'     => 'H:i:s',
            ],
        ];

        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }
    }
}
