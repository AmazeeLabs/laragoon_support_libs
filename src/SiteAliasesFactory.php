<?php

namespace Amazee\LaragoonSupport;

use Symfony\Component\Yaml\Yaml;

class SiteAliasesFactory
{

    private static $siteAliases = null;

    /**
     * This function is essentially a fallback to try extract the default
     * information about a project from the environment and/or .lagoon.yml files
     */
    public static function getSiteAliasInstance(
      $lagoonDotYml = null,
      $reload = false
    ) {
        if (is_null(self::$siteAliases) || $reload == true) {
            list($projectName, $siteAliasDefaults) = self::loadDefaults($lagoonDotYml);
            self::$siteAliases = new SiteAliases($projectName,
              $siteAliasDefaults);
        }

        return self::$siteAliases;
    }

    /**
     * @param $lagoonDotYml
     *
     * @return array
     */
    public static function loadDefaults($lagoonDotYml = null)
    {
        $siteAliasDefaults = [];

        //Project Name
        $projectName = null;
        if (getenv('LAGOON_PROJECT')) {
            $projectName = getenv('LAGOON_PROJECT');
        }

        //SSH
        $unexplodedSSH = null;
        if (getenv('LAGOON_OVERRIDE_SSH')) {
            $unexplodedSSH = getenv('LAGOON_OVERRIDE_SSH');
        }

        //GraphQL Endpoint
        $api = null;
        if (getenv('LAGOON_OVERRIDE_API')) {
            $api = getenv('LAGOON_OVERRIDE_API');
        }


        if (!is_null($lagoonDotYml) && file_exists($lagoonDotYml)) {
            $lagoonYml = Yaml::parse(file_get_contents($lagoonDotYml));

            if (!$projectName && $lagoonYml['project']) {
                $projectName = $lagoonYml['project'];
            }

            if (empty($siteAliasDefaults['jwtSSHHost']) || empty($siteAliasDefaults['jwtSSHPort'])) {

                $unexplodedSSH = !empty($lagoonYml['ssh']) ? $lagoonYml['ssh'] : $unexplodedSSH;
                $unexplodedSSH = !empty($lagoonYml['endpoint']) && is_null($unexplodedSSH) ? $lagoonYml['endpoint'] : $unexplodedSSH;
            }

            if (empty($api) && $lagoonYml['api']) {
                $api = $lagoonYml['api'];
            }
        }


        if (!is_null($unexplodedSSH)) {
            list($siteAliasDefaults['jwtSSHHost'], $siteAliasDefaults['jwtSSHPort']) = explode(":",
              $unexplodedSSH);
        }

        if (!empty($api)) {
            if (count(explode(":", $api)) == 2) {
                list ($api_host, $api_port) = explode(":", $api);
                $apiUrl = "$api_host:$api_port/graphql";
            } else {
                $apiUrl = "$api/graphql";
            }
        }

        if (isset($apiUrl)) {
            $siteAliasDefaults['graphQLEndpoint'] = $apiUrl;
        }

        return [$projectName, $siteAliasDefaults];
    }
}
