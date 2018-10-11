<?php

use Amazee\LaragoonSupport\SiteAliasesFactory;
use PHPUnit\Framework\TestCase;

class SiteAliasesFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_read_defaults_from_a_provided_lagoonyml_file() {
        list($projectName, $lagoonDetails) = SiteAliasesFactory::loadDefaults(__DIR__ . "/assets/test.lagoon.yml");
        $this->assertEquals('laragoon', $projectName);
        $this->assertEquals('api-lagoon-master.lagoon.ch.amazee.io', $lagoonDetails['jwtSSHHost']);
        $this->assertEquals('31472', $lagoonDetails['jwtSSHPort']);
        $this->assertEquals('api-lagoon-master.lagoon.ch.amazee.io:80/graphql', $lagoonDetails['graphQLEndpoint']);
    }

    /**
     * @test
     */
    public function it_should_pull_defaults_from_the_environment() {
        $envProjectName = 'ENV_PROJECT_NAME';
        putenv('LAGOON_PROJECT='.$envProjectName);

        $envSSHHost = 'ENV_SSH_HOST';
        $envSSHPort = '7777';
        putenv('LAGOON_OVERRIDE_SSH=' . $envSSHHost . ':' . $envSSHPort);

        $envAPI = 'test.com';
        putenv('LAGOON_OVERRIDE_API=' . $envAPI);

        list($projectName, $lagoonDetails) = SiteAliasesFactory::loadDefaults(__DIR__ . "/assets/test.lagoon.yml");


        $this->assertEquals($envProjectName, $projectName);


        $this->assertEquals($envSSHHost, $lagoonDetails['jwtSSHHost']);
        $this->assertEquals($envSSHPort, $lagoonDetails['jwtSSHPort']);
        $this->assertEquals($envAPI . '/graphql', $lagoonDetails['graphQLEndpoint']);
    }

}
