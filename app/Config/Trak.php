<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Connection settings for the TrakCare (InterSystems IRIS) ODBC source that
 * ServicesModel reads patient demographics from.
 *
 * The CodeIgniter 3 version hard-coded these in Services_model. They now live
 * here so they can be overridden per environment from .env, e.g.
 *
 *     trak.server = 10.11.94.27
 *     trak.username = SOME_USER
 *     trak.password = SOME_PASSWORD
 */
class Trak extends BaseConfig
{
    public string $driver = '{InterSystems IRIS ODBC35}';

    public string $server = '';

    public string $port = '56772';

    public string $database = 'TRAK';

    public string $username = '';

    public string $password = '';

    /**
     * Builds the ODBC connection string used by odbc_connect().
     */
    public function connectionString(): string
    {
        return sprintf(
            'Driver=%s;Server=%s;PORT=%s;Database=%s',
            $this->driver,
            $this->server,
            $this->port,
            $this->database
        );
    }
}
