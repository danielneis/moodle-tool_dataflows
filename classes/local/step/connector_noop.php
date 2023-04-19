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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_dataflows\local\step;

/**
 * No-op connector step type
 *
 * @package    tool_dataflows
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_noop extends connector_step {

    /** @var int[] number of output flows (min, max). */
    protected $outputflows = [0, 1];

    /** @var int[] number of output connectors (min, max) */
    protected $outputconnectors = [0, 1];

    /**
     * Executes the step
     *
     * This will logs the input via debugging and passes the input value as-is to the output.
     *
     * @param mixed $input
     * @return mixed $output
     */
    public function execute($input = null) {
        $output = $input;
        return $output;
    }
}
