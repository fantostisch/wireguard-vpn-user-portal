<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

use LC\Common\Http\Exception\HttpException;
use LC\Common\HttpClient\HttpClientInterface;
use LC\Common\Json;

class WGDaemonClient
{
    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string */
    private $baseUrl;

    /**
     * @param string $baseUrl
     */
    public function __construct(HttpClientInterface $httpClient, $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->httpClient = $httpClient;
    }

    /**
     * @param string $userId
     *
     * @throws HttpException
     *
     * @return array<string, WGClientConfig>
     */
    public function getConfigs($userId)
    {
        $result = $this->httpClient->get($this->baseUrl.'/configs', ['user_id' => $userId]);
        $responseCode = $result->getCode();
        $responseString = $result->getBody();
        $this->assertSuccess([], $responseCode, $responseString);

        $decodedJson = Json::decode($responseString);

        $errorMessage = 'Invalid configurations received from WG Daemon';
        /** @var array<string,\LC\Portal\WireGuard\WGClientConfig> $result */
        $result = self::createTypeThrowIfError('array<string,\LC\Portal\WireGuard\WGClientConfig>', $decodedJson, $errorMessage);

        return $result;
    }

    /**
     * @param string $userId
     * @param string $name
     *
     * @return CreateResponse
     */
    public function createConfig($userId, $name)
    {
        $result = $this->httpClient->post($this->baseUrl.'/create_config_and_key_pair', [], ['user_id' => $userId, 'name' => $name]);
        $responseCode = $result->getCode();
        $responseString = $result->getBody();
        $this->assertSuccess([], $responseCode, $responseString);

        $decodedJson = Json::decode($responseString);

        $errorMessage = 'Invalid response from WG Daemon';
        /** @var \LC\Portal\WireGuard\CreateResponse $result */
        $result = self::createTypeThrowIfError('\LC\Portal\WireGuard\CreateResponse', $decodedJson, $errorMessage);

        return $result;
    }

    /**
     * @param string $userId
     * @param string $publicKey
     *
     * @return void
     */
    public function deleteConfig($userId, $publicKey)
    {
        $result = $this->httpClient->post($this->baseUrl.'/delete_config', ['user_id' => $userId, 'public_key' => $publicKey], []);
        $responseCode = $result->getCode();
        $responseString = $result->getBody();
        $this->assertSuccess(['config_not_found'], $responseCode, $responseString);
    }

    /**
     * @param string $userId
     *
     * @return void
     */
    public function disableUser($userId)
    {
        $result = $this->httpClient->post($this->baseUrl.'/disable_user', ['user_id' => $userId], []);
        $responseCode = $result->getCode();
        $responseString = $result->getBody();
        $this->assertSuccess(['user_already_disabled'], $responseCode, $responseString);
    }

    /**
     * @param string $userId
     *
     * @return void
     */
    public function enableUser($userId)
    {
        $result = $this->httpClient->post($this->baseUrl.'/enable_user', ['user_id' => $userId], []);
        $responseCode = $result->getCode();
        $responseString = $result->getBody();
        $this->assertSuccess(['user_already_enabled'], $responseCode, $responseString);
    }

    /**
     * @psalm-type userID=string
     *
     * @return array<userID, array<WGClientConnection>>
     */
    public function getClientConnections()
    {
        $result = $this->httpClient->get($this->baseUrl.'/client_connections', []);
        $responseCode = $result->getCode();
        $responseString = $result->getBody();
        $this->assertSuccess([], $responseCode, $responseString);

        $decodedJson = Json::decode($responseString);

        $errorMessage = 'Invalid connections received from WG Daemon';
        /** @var array<array<\LC\Portal\WireGuard\WGClientConnection>> $result */
        $result = self::createTypeThrowIfError('array<string,array<\LC\Portal\WireGuard\WGClientConnection>>', $decodedJson, $errorMessage);

        return $result;
    }

    /**
     * @param array<"config_not_found" | "user_already_enabled" | "user_already_disabled"> $allowedErrors
     * @param int                                                                          $responseCode
     * @param string                                                                       $responseString
     *
     * @return void
     */
    private static function assertSuccess($allowedErrors, $responseCode, $responseString)
    {
        if (200 === $responseCode) {
            return;
        }
        if (400 === $responseCode) {
            $decodedJson = Json::decode($responseString);
            $errorMessage = 'Got an error from the WG Daemon but could not decode it.';
            /** @var WGDaemonError $result */
            $error = self::createTypeThrowIfError('\LC\Portal\WireGuard\WGDaemonError', $decodedJson, $errorMessage);
            if (\in_array($error->errorType, $allowedErrors, true)) {
                return;
            } else {
                throw new HttpException('Got an error from the WG Daemon which was not allowed: "'.$error->errorType.'". Response:'.$responseString, 500);
            }
        }
        throw new HttpException('Unexpected response code from WireGuard Daemon: "'.$responseCode.'". Response: '.$responseString, 500);
    }

    /**
     * @param string $typeName
     * @param mixed  $value
     * @param string $errorMessage
     *
     * @throws HttpException
     *
     * @return mixed
     */
    private static function createTypeThrowIfError($typeName, $value, $errorMessage)
    {
        $result = TypeCreator::createType($typeName, $value);

        if (!ValidationError::isValid($result)) {
            throw new HttpException((new ValidationError($errorMessage, $result))->__toString(), 500);
        }

        return $result;
    }
}
