<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_clasemeet\google;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Google service account with domain-wide delegation: signs a JWT and exchanges it for an access token
 * that acts on behalf of a Workspace user (the teacher).
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_account {
    /** @var string[] Scopes authorised in the Workspace admin console for this service account. */
    public const DEFAULT_SCOPES = [
        'https://www.googleapis.com/auth/meetings.space.created',
        'https://www.googleapis.com/auth/meetings.space.settings',
        'https://www.googleapis.com/auth/meetings.space.readonly',
        'https://www.googleapis.com/auth/drive.readonly',
        'https://www.googleapis.com/auth/calendar.events',
    ];

    /** @var string Extra scope needed to share recording files with the domain. */
    public const SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive';

    /** @var array Decoded key file. */
    private array $key;

    /**
     * Load the key file configured in the plugin settings.
     *
     * @return self
     */
    public static function from_config(): self {
        $file = (string) get_config('mod_clasemeet', 'credentialsfile');
        return new self($file);
    }

    /**
     * @param string $file Absolute path of the service account JSON key.
     */
    public function __construct(string $file) {
        if ($file === '' || !is_readable($file)) {
            throw new moodle_exception('errorcredentials', 'mod_clasemeet', '', $file ?: '(empty)');
        }
        $key = json_decode((string) file_get_contents($file), true);
        foreach (['client_email', 'private_key', 'token_uri'] as $field) {
            if (empty($key[$field])) {
                throw new moodle_exception('errorcredentials', 'mod_clasemeet', '', "missing $field");
            }
        }
        $this->key = $key;
    }

    /**
     * Scopes to request, from the plugin settings (falls back to the defaults).
     *
     * @return string[]
     */
    public static function configured_scopes(): array {
        $raw = (string) get_config('mod_clasemeet', 'scopes');
        $scopes = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw))));
        return $scopes ?: self::DEFAULT_SCOPES;
    }

    /**
     * Access token acting as $subject (cached for ~55 minutes).
     *
     * @param string $subject Workspace user to impersonate.
     * @param string[]|null $scopes Scopes to request; defaults to the configured ones.
     * @return string
     */
    public function access_token(string $subject, ?array $scopes = null): string {
        $scopes = $scopes ?? self::configured_scopes();
        $cache = \cache::make('mod_clasemeet', 'tokens');
        $cachekey = sha1($this->key['client_email'] . '|' . $subject . '|' . implode(' ', $scopes));
        $cached = $cache->get($cachekey);
        if (is_array($cached) && !empty($cached['token']) && $cached['expires'] > time() + 60) {
            return $cached['token'];
        }

        $now = time();
        $jwt = $this->sign_jwt([
            'iss' => $this->key['client_email'],
            'sub' => $subject,
            'scope' => implode(' ', $scopes),
            'aud' => $this->key['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
        $response = $curl->post($this->key['token_uri'], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]));
        $data = json_decode((string) $response, true);
        if (empty($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? ($curl->error ?: 'unknown error');
            throw new moodle_exception('errortoken', 'mod_clasemeet', '',
                (object) ['email' => $subject, 'error' => $error]);
        }

        $cache->set($cachekey, [
            'token' => $data['access_token'],
            'expires' => $now + (int) ($data['expires_in'] ?? 3600),
        ]);
        return $data['access_token'];
    }

    /**
     * RS256-signed JWT for the given claims.
     *
     * @param array $claims
     * @return string
     */
    private function sign_jwt(array $claims): string {
        $segments = [
            self::b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::b64url(json_encode($claims)),
        ];
        $input = implode('.', $segments);
        $signature = '';
        if (!openssl_sign($input, $signature, $this->key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new moodle_exception('errorcredentials', 'mod_clasemeet', '', 'cannot sign with private_key');
        }
        return $input . '.' . self::b64url($signature);
    }

    /**
     * Base64url without padding.
     *
     * @param string $data
     * @return string
     */
    private static function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
