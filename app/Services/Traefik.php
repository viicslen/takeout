<?php

namespace App\Services;

use App\Shell\Docker;
use App\Shell\Environment;
use App\Shell\Shell;
use Exception;

class Traefik extends BaseService
{
    protected static $category = Category::TOOLS;

    protected $imageName = 'traefik';

    protected $defaultTag = 'v2.10';

    protected $defaultPort = 8080;

    protected $prompts = [
        [
            'shortname' => 'config_dir',
            'prompt' => 'What is the configuration directory?',
        ],
        [
            'shortname' => 'web_port',
            'prompt' => 'What is the web port?',
            'default' => '80',
        ],
        [
            'shortname' => 'websecure_port',
            'prompt' => 'What is the websecure port?',
            'default' => '443',
        ]
    ];

    protected $dockerRunTemplate = '-p "${:port}":8080 -p "${:web_port}":80 -p "${:websecure_port}":443 \
        -v "${:config_dir}":/etc/traefik \
        -v /var/run/docker.sock:/var/run/docker.sock \
        "${:organization}"/"${:image_name}":"${:tag}" \
        --api.insecure=true --providers.docker=true --entryPoints.web.address=:80 --entryPoints.websecure.address=:443 --providers.file.directory=/etc/traefik/conf --providers.file.watch=true';


    public function __construct(Shell $shell, Environment $environment, Docker $docker)
    {
        parent::__construct($shell, $environment, $docker);

        $home = $this->homeDirectory();

        $this->defaultPrompts = array_map(function ($prompt) {
            if ($prompt['shortname'] === 'tag') {
                $prompt['default'] = $this->defaultTag;
            }

            return $prompt;
        }, $this->defaultPrompts);

        $this->prompts = array_map(function ($prompt) use ($home) {
            if ($prompt['shortname'] === 'config' && ! empty($home)) {
                $prompt['default'] = "$home/.config/traefik";
            }

            return $prompt;
        }, $this->prompts);
    }

    protected function homeDirectory(): ?string
    {
        // Cannot use $_SERVER superglobal since that's empty during UnitUnishTestCase
        // getenv('HOME') isn't set on Windows and generates a Notice.
        $home = getenv('HOME');

        if (! empty($home)) {
            // home should never end with a trailing slash.
            $home = rtrim($home, '/');
        }

        elseif (! empty($_SERVER['HOMEDRIVE']) && ! empty($_SERVER['HOMEPATH'])) {
            // home on windows
            $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
            // If HOMEPATH is a root directory the path can end with a slash. Make sure
            // that doesn't happen.
            $home = rtrim($home, '\\/');
        }

        return empty($home) ? null : $home;
    }

    protected function prompts(): void
    {
        parent::prompts();

        if (empty($this->promptResponses['config'])) {
            throw new Exception('You must specify a configuration directory.');
        }
    }
}