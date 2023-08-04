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

// CATALYST CUSTOM: WR409112 - Adds a new webservice function to call from block_myoverview.
// The majority of this class is copied from the get_enrolled_courses_by_timeline_classification_*
// functions in course/externallib.php with some minor changes.

/**
 * Webservice class for get_courses for the myoverview block.
 * *
 * @package    block_myoverview
 * @copyright  2023 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Conn Warwicker <conn.warwicker@catalyst-eu.net>
 */

namespace block_myoverview\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

class get_courses extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            array(
                'classification' => new external_value(PARAM_ALPHA, 'future, inprogress, or past'),
                'limit' => new external_value(PARAM_INT, 'Result set limit', VALUE_DEFAULT, 0),
                'offset' => new external_value(PARAM_INT, 'Result set offset', VALUE_DEFAULT, 0),
                'sort' => new external_value(PARAM_TEXT, 'Sort string', VALUE_DEFAULT, null),
                'customfieldname' => new external_value(PARAM_ALPHANUMEXT, 'Used when classification = customfield',
                    VALUE_DEFAULT, null),
                'customfieldvalue' => new external_value(PARAM_RAW, 'Used when classification = customfield',
                    VALUE_DEFAULT, null),
                'searchvalue' => new external_value(PARAM_RAW, 'The value a user wishes to search against',
                    VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Define the parameters expected to return from the function
     *
     * @return external_single_structure
     */
    public static function execute_returns() : external_single_structure {
        return new external_single_structure(
            array
            (
                'courses' => new external_multiple_structure(
                    block_myoverview_course_summary_exporter::get_read_structure(), 'Course'
                ),
                'nextoffset' => new external_value(PARAM_INT, 'Offset for the next request')
            )
        );
    }

    /**
     * Execute the get_courses webservice function
     *
     * @param  string $classification past, inprogress, or future
     * @param  int $limit Result set limit
     * @param  int $offset Offset the full course set before timeline classification is applied
     * @param  string $sort SQL sort string for results
     * @param  string $customfieldname
     * @param  string $customfieldvalue
     * @param  string $searchvalue
     * @return array
     * @throws  invalid_parameter_exception
     */
    public static function execute(
        string $classification,
        int $limit = 0,
        int $offset = 0,
        string $sort = null,
        string $customfieldname = null,
        string $customfieldvalue = null,
        string $searchvalue = null
    ) : array {

        global $CFG, $USER, $PAGE;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(),
            array(
                'classification' => $classification,
                'limit' => $limit,
                'offset' => $offset,
                'sort' => $sort,
                'customfieldvalue' => $customfieldvalue,
                'searchvalue' => $searchvalue,
            )
        );

        $classification = $params['classification'];
        $limit = $params['limit'];
        $offset = $params['offset'];
        $sort = $params['sort'];
        $customfieldvalue = $params['customfieldvalue'];
        $searchvalue = clean_param($params['searchvalue'], PARAM_TEXT);

        switch($classification) {
            case COURSE_TIMELINE_ALLINCLUDINGHIDDEN:
                break;
            case COURSE_TIMELINE_ALL:
                break;
            case COURSE_TIMELINE_PAST:
                break;
            case COURSE_TIMELINE_INPROGRESS:
                break;
            case COURSE_TIMELINE_FUTURE:
                break;
            case COURSE_FAVOURITES:
                break;
            case COURSE_TIMELINE_HIDDEN:
                break;
            case COURSE_TIMELINE_SEARCH:
                break;
            case COURSE_CUSTOMFIELD:
                break;
            default:
                throw new \invalid_parameter_exception('Invalid classification');
        }

        self::validate_context(\core\context\user::instance($USER->id));

        $requiredproperties = block_myoverview_course_summary_exporter::define_properties();
        $fields = join(',', array_keys($requiredproperties));
        $hiddencourses = get_hidden_courses_on_timeline();
        $courses = [];

        // If the timeline requires really all courses, get really all courses.
        if ($classification == COURSE_TIMELINE_ALLINCLUDINGHIDDEN) {
            $courses = course_get_enrolled_courses_for_logged_in_user(0, $offset, $sort, $fields, COURSE_DB_QUERY_LIMIT);

            // Otherwise if the timeline requires the hidden courses then restrict the result to only $hiddencourses.
        } else if ($classification == COURSE_TIMELINE_HIDDEN) {
            $courses = course_get_enrolled_courses_for_logged_in_user(0, $offset, $sort, $fields,
                COURSE_DB_QUERY_LIMIT, $hiddencourses);

            // Otherwise get the requested courses and exclude the hidden courses.
        } else if ($classification == COURSE_TIMELINE_SEARCH) {
            // Prepare the search API options.
            $searchcriteria['search'] = $searchvalue;
            $options = ['idonly' => true];
            $courses = course_get_enrolled_courses_for_logged_in_user_from_search(
                0,
                $offset,
                $sort,
                $fields,
                COURSE_DB_QUERY_LIMIT,
                $searchcriteria,
                $options
            );
        } else {
            $courses = course_get_enrolled_courses_for_logged_in_user(0, $offset, $sort, $fields,
                COURSE_DB_QUERY_LIMIT, [], $hiddencourses);
        }

        $favouritecourseids = [];
        $ufservice = \core_favourites\service_factory::get_service_for_user_context(\core\context\user::instance($USER->id));
        $favourites = $ufservice->find_favourites_by_type('core_course', 'courses');

        if ($favourites) {
            $favouritecourseids = array_map(
                function($favourite) {
                    return $favourite->itemid;
                }, $favourites);
        }

        if ($classification == COURSE_FAVOURITES) {
            list($filteredcourses, $processedcount) = course_filter_courses_by_favourites(
                $courses,
                $favouritecourseids,
                $limit
            );
        } else if ($classification == COURSE_CUSTOMFIELD) {
            list($filteredcourses, $processedcount) = course_filter_courses_by_customfield(
                $courses,
                $customfieldname,
                $customfieldvalue,
                $limit
            );
        } else {
            list($filteredcourses, $processedcount) = course_filter_courses_by_timeline_classification(
                $courses,
                $classification,
                $limit
            );
        }

        $renderer = $PAGE->get_renderer('core');
        $formattedcourses = array_map(function($course) use ($renderer, $favouritecourseids) {

            if ($course == null) {
                return;
            }

            \context_helper::preload_from_record($course);
            $context = \core\context\course::instance($course->id);
            $isfavourite = false;

            if (in_array($course->id, $favouritecourseids)) {
                $isfavourite = true;
            }

            $exporter = new block_myoverview_course_summary_exporter($course, [
                'context' => $context, 'isfavourite' => $isfavourite
            ]);
            return $exporter->export($renderer);

        }, $filteredcourses);

        $formattedcourses = array_filter($formattedcourses, function($course) {
            if ($course != null) {
                return $course;
            }
        });

        return [
            'courses' => $formattedcourses,
            'nextoffset' => $offset + $processedcount
        ];

    }

}
