<?php

namespace Thruway\Authentication;

use React\EventLoop\LoopInterface;
use Thruway\Peer\Client;

/**
 * Abstract class AbstractAuthProviderClient
 *
 * @package Thruway\Authentication
 */
abstract class AbstractAuthProviderClient extends Client
{
    /**
     *
     * @var array
     */
    protected $authRealms;

    /**
     * Constructor
     *
     * @param array $authRealms
     * @param \React\EventLoop\LoopInterface $loop
     */
    public function __construct(Array $authRealms, LoopInterface $loop = null)
    {

        $this->authRealms = $authRealms;

        /*
         * Set authorization the realm. Defaults to "thruway.auth"
         *
         * This realm is only used between the Authentication Provider Client and the Authentication Manager Client on the server.
         *
         */
        parent::__construct('thruway.auth', $loop);

    }

    /**
     * Process HelloMessage
     *
     * @param array $args
     * @return array<string|array>
     */
    public function processHello(array $args)
    {

        return ["CHALLENGE", ["challenge" => new \stdClass(), "challenge_method" => $this->getMethodName()]];
    }

    /**
     * Handles session start
     *
     * @param \Thruway\AbstractSession $session
     * @param \Thruway\Transport\TransportProviderInterface $transport
     */
    public function onSessionStart($session, $transport)
    {
        $this->getCallee()->register(
            $session,
            "thruway.auth.{$this->getMethodName()}.onhello",
            [$this, 'processHello'],
            ["replace_orphaned_session" => "yes"]
        )
            ->then(function () use ($session) {
                $this->getCallee()->register(
                    $session,
                    "thruway.auth.{$this->getMethodName()}.onauthenticate",
                    [$this, 'preProcessAuthenticate'],
                    ["replace_orphaned_session" => "yes"]
                )
                    ->then(function () use ($session) {
                        $this->getCaller()->call($session,
                            'thruway.auth.registermethod',
                            [
                                $this->getMethodName(),
                                [
                                    "onhello"        => "thruway.auth.{$this->getMethodName()}.onhello",
                                    "onauthenticate" => "thruway.auth.{$this->getMethodName()}.onauthenticate"
                                ],
                                $this->getAuthRealms()
                            ]
                        )
                            ->then(function ($args) {
                                $this->manager->info(print_r($args, true));
                            });
                    });
            });
    }

    /**
     * Pre process AuthenticateMessage
     * Extract and validate arguments
     *
     * @param array $args
     * @return array
     */
    public function preProcessAuthenticate(array $args)
    {

        $signature = isset($args['signature']) ? $args['signature'] : null;
        $extra     = isset($args['extra']) ? $args['extra'] : null;

        if (!$signature) {
            return ["ERROR"];
        }

        return $this->processAuthenticate($signature, $extra);

    }

    /**
     * Process AuthenticateMessage
     * Check authenticate and return ["SUCCESS"] and ["FAILURE"]
     *
     * @param mixed $signature
     * @param mixed $extra
     * @return array
     */
    public function processAuthenticate($signature, $extra = null)
    {

        return ["SUCCESS"];

    }

    /**
     * Get list supported realms
     *
     * @return array
     */
    public function getAuthRealms()
    {
        return $this->authRealms;
    }

    /**
     * Set list supported realms
     *
     * @param array $authRealms
     */
    public function setAuthRealms($authRealms)
    {
        $this->authRealms = $authRealms;
    }

    /**
     * @return mixed
     */
    abstract public function getMethodName();

} 