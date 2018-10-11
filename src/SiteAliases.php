<?php

namespace Amazee\LaragoonSupport;

class SiteAliases
{

    private $projectName = null;

    private $aliases = null;

    private static $jwtToken = null;

    private $parameters = [
      'jwtSSHPort' => 32222,
      'jwtSSHHost' => 'ssh.lagoon.amazeeio.cloud',
      'graphQLEndpoint' => 'https://api.lagoon.amazeeio.cloud/graphql',
      'jitLoad' => true,
    ];

    public function __construct($projectName, $defaultOverrides = [])
    {

        if (empty($projectName)) {
            throw new \Exception("Project Name cannot be empty");
        }

        $this->projectName = $projectName;

        if (is_array($defaultOverrides) && count($defaultOverrides) > 0) {
            foreach ($defaultOverrides as $key => $override) {
                $this->parameters[$key] = $override;
            }
        }

        if ($this->parameters['jitLoad'] === false) {
            $this->getAliases();
        }
    }

    private function getJWTToken()
    {
        if (is_null(self::$jwtToken)) {
            $command = sprintf('ssh -p %d -o LogLevel=ERROR -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -t lagoon@%s token 2>&1',
              $this->parameters['jwtSSHPort'], $this->parameters['jwtSSHHost']);
            exec($command, $tokenArray, $returnValue);
            if ($returnValue !== 0) {
                throw new \Exception("Could not load API JWT Token, error was: '" . implode(",",
                    $tokenArray));
            }

            self::$jwtToken = $tokenArray[0];
        }

        return self::$jwtToken;
    }

    private function getSiteAliases($projectName, $jwt_token)
    {
        $query = sprintf('{
  project:projectByName(name: "%s") {
    environments {
      name
      openshiftProjectName
    }
  }
}', $projectName);

        $curl = curl_init($this->parameters['graphQLEndpoint']);

        // Build up the curl options for the GraphQL query. When using the content type
        // 'application/json', graphql-express expects the query to be in the json
        // encoded post body beneath the 'query' property.
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
          'Content-Type: application/json',
          "Authorization: Bearer $jwt_token",
        ]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
          'query' => $query,
        ]));

        $response = curl_exec($curl);

        if ($response === false) {
            $info = var_export(curl_getinfo($curl), true);
            $error = curl_error($curl);
            curl_close($curl);
            throw new \Exception("Error connecting to graphQL endpoint: " . $error);
        }

        curl_close($curl);
        return $this->parseAliases(json_decode($response));
    }

    private function parseAliases($aliasObject)
    {
        $environments = $aliasObject->data->project->environments;
        // Default server definition, which has no site specific elements
        $defaults = [
          'command-specific' => [
            'sql-sync' => [
              'no-ordered-dump' => TRUE
            ],
          ],
          'ssh-options' => "-o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no",
        ];

        $sshHost = $this->parameters['jwtSSHHost'];
        $sshPort = $this->parameters['jwtSSHPort'];

        return array_reduce($environments, function ($carry, $environment) use ($defaults, $sshHost, $sshPort) {
            $site_name = str_replace('/','-',$environment->name);
            $site_host = 'localhost';

            $alias = [
                'remote-host' => "$sshHost",
                'remote-user' => "$environment->openshiftProjectName",
                //'root' => "$drupal_path",
                'ssh-options' => "-o LogLevel=ERROR -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -p $sshPort",
              ] + $defaults;

            return $carry + [$site_name => $alias];
        }, []);
    }

    public function loadAliases()
    {
        $this->aliases = $this->getSiteAliases($this->projectName,
          $this->getJWTToken());
    }

    public function getAliases()
    {
        if (is_null($this->aliases)) {
            $this->loadAliases();
        }
        return $this->aliases;
    }
}