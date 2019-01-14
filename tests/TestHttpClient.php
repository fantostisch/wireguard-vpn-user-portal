<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use RuntimeException;
use SURFnet\VPN\Common\HttpClient\HttpClientInterface;

class TestHttpClient implements HttpClientInterface
{
    public function get($requestUri, array $getData = [], array $requestHeaders = [])
    {
        switch ($requestUri) {
            case 'serverClient/profile_list':
                return self::wrap(
                    'profile_list',
                    [
                        'internet' => [
                            'hideProfile' => false,
                            'enableAcl' => false,
                            'displayName' => 'Internet Access',
                            'twoFactor' => false,
                            'tlsProtection' => 'tls-crypt',
                            'vpnProtoPorts' => [
                                'udp/1194',
                                'udp/1195',
                                'tcp/1194',
                                'tcp/443',
                            ],
                            'hostName' => 'vpn.example',
                            'enableCompression' => true,
                        ],
                    ]
                );
            case 'serverClient/client_certificate_list?user_id=foo':
                return self::wrap(
                    'client_certificate_list',
                    [
                        [
                            'display_name' => 'Foo',
                            'valid_from' => 123456,
                            'valid_to' => 2345567,
                            'client_id' => null,
                        ],
                    ]
                );
            case 'serverClient/user_messages?user_id=foo':
                return self::wrap('user_messages', []);
            case 'serverClient/system_messages?message_type=motd':
                return self::wrap('system_messages', [['id' => 1, 'message_type' => 'motd', 'message_body' => 'Hello World!']]);
            case 'serverClient/has_totp_secret?user_id=foo':
                return self::wrap('has_totp_secret', false);
            case 'serverClient/is_disabled_user?user_id=foo':
                return self::wrap('is_disabled_user', false);
            case 'serverClient/client_certificate_info?common_name=12345678901234567890123456789012':
                return self::wrap('client_certificate_info', ['display_name' => 'Foo']);
            case 'serverClient/server_info':
                return self::wrap(
                    'server_info',
                    [
                        'ca' => 'CAPEM',
                        'ta' => 'TAKEY',
                    ]
                );
            default:
                throw new RuntimeException(sprintf('unexpected requestUri "%s"', $requestUri));
        }
    }

    public function post($requestUri, array $postData = [], array $requestHeaders = [])
    {
        switch ($requestUri) {
            case 'serverClient/add_client_certificate':
                return self::wrap(
                    'add_client_certificate',
                    [
                        'valid_from' => 12345678,
                        'valid_to' => '23456789',
                        'certificate' => 'CERTPEM',
                        'private_key' => 'KEYPEM',
                    ]
                );
            case 'serverClient/delete_client_certificate':
                return self::wrap('delete_client_certificate', true);
            case 'serverClient/kill_client':
                return self::wrap('kill_client', true);
            case 'serverClient/set_voot_token':
                return self::wrap('set_voot_token', true);
            default:
                throw new RuntimeException(sprintf('unexpected requestUri "%s"', $requestUri));
        }
    }

    private static function wrap($key, $responseData, $statusCode = 200)
    {
        return [
            $statusCode,
            [
                $key => [
                    'ok' => true,
                    'data' => $responseData,
                ],
            ],
        ];
    }

    private static function wrapError($key, $errorMessage, $statusCode = 200)
    {
        return [
            $statusCode,
            [
                $key => [
                    'ok' => false,
                    'error' => $errorMessage,
                ],
            ],
        ];
    }
}
