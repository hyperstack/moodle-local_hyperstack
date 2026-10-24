<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_hyperstack\api;

/**
 * A Hyperstack API call that did not succeed.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_exception extends \moodle_exception {
    /** @var int HTTP status code, or 0 when Hyperstack could not be reached. */
    public int $httpcode;

    /** @var string The API's error_code, or a code of our own (invalid_key, network, server). */
    public string $apicode;

    /** @var bool True when trying again later can work. */
    public bool $retry;

    /**
     * Constructor.
     *
     * @param int $httpcode HTTP status code, 0 for no answer
     * @param string $apicode Error code
     * @param string $message The reason, as Hyperstack gave it
     * @param bool $retry True when trying again later can work
     * @param string|null $debuginfo Technical detail for the task log, not shown to teachers
     */
    public function __construct(int $httpcode, string $apicode, string $message, bool $retry, ?string $debuginfo = null) {
        $this->httpcode = $httpcode;
        $this->apicode = $apicode;
        $this->retry = $retry;
        parent::__construct('apierror', 'local_hyperstack', '', $message, $debuginfo);
    }

    /**
     * The reason, as Hyperstack gave it.
     *
     * @return string
     */
    public function get_reason(): string {
        return (string) $this->a;
    }
}
