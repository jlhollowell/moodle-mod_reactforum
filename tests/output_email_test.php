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

/**
 * The module reactforums tests
 *
 * @package    mod_reactforum
 * @copyright  2017 (C) VERSION2, INC.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the reactforum output/email class.
 *
 * @copyright  2017 (C) VERSION2, INC.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_reactforum_output_email_testcase extends advanced_testcase {
    /**
     * Data provider for the postdate function tests.
     */
    public function postdate_provider() {
        return array(
            'Timed discussions disabled, timestart unset' => array(
                'globalconfig'      => array(
                    'reactforum_enabletimedposts' => 0,
                ),
                'reactforumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                ),
                'expectation'       => 1000,
            ),
            'Timed discussions disabled, timestart set and newer' => array(
                'globalconfig'      => array(
                    'reactforum_enabletimedposts' => 0,
                ),
                'reactforumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                    'timestart' => 2000,
                ),
                'expectation'       => 1000,
            ),
            'Timed discussions disabled, timestart set but older' => array(
                'globalconfig'      => array(
                    'reactforum_enabletimedposts' => 0,
                ),
                'reactforumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                    'timestart' => 500,
                ),
                'expectation'       => 1000,
            ),
            'Timed discussions enabled, timestart unset' => array(
                'globalconfig'      => array(
                    'reactforum_enabletimedposts' => 1,
                ),
                'reactforumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                ),
                'expectation'       => 1000,
            ),
            'Timed discussions enabled, timestart set and newer' => array(
                'globalconfig'      => array(
                    'reactforum_enabletimedposts' => 1,
                ),
                'reactforumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                    'timestart' => 2000,
                ),
                'expectation'       => 2000,
            ),
            'Timed discussions enabled, timestart set but older' => array(
                'globalconfig'      => array(
                    'reactforum_enabletimedposts' => 1,
                ),
                'reactforumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                    'timestart' => 500,
                ),
                'expectation'       => 1000,
            ),
        );
    }

    /**
     * Test for the reactforum email renderable postdate.
     *
     * @dataProvider postdate_provider
     *
     * @param array  $globalconfig      The configuration to set on $CFG
     * @param array  $reactforumconfig       The configuration for this reactforum
     * @param array  $postconfig        The configuration for this post
     * @param array  $discussionconfig  The configuration for this discussion
     * @param string $expectation       The expected date
     */
    public function test_postdate($globalconfig, $reactforumconfig, $postconfig, $discussionconfig, $expectation) {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        // Apply the global configuration.
        foreach ($globalconfig as $key => $value) {
            $CFG->$key = $value;
        }

        // Create the fixture.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $reactforum = $this->getDataGenerator()->create_module('reactforum', (object) array('course' => $course->id));
        $cm = get_coursemodule_from_instance('reactforum', $reactforum->id, $course->id, false, MUST_EXIST);

        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create a new discussion.
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_reactforum')->create_discussion(
            (object) array_merge($discussionconfig, array(
                'course'    => $course->id,
                'reactforum'     => $reactforum->id,
                'userid'    => $user->id,
            )));

        // Apply the discussion configuration.
        // Some settings are ignored by the generator and must be set manually.
        $discussion = $DB->get_record('reactforum_discussions', array('id' => $discussion->id));
        foreach ($discussionconfig as $key => $value) {
            $discussion->$key = $value;
        }
        $DB->update_record('reactforum_discussions', $discussion);

        // Apply the post configuration.
        // Some settings are ignored by the generator and must be set manually.
        $post = $DB->get_record('reactforum_posts', array('discussion' => $discussion->id));
        foreach ($postconfig as $key => $value) {
            $post->$key = $value;
        }
        $DB->update_record('reactforum_posts', $post);

        // Create the renderable.
        $renderable = new mod_reactforum\output\reactforum_post_email(
                $course,
                $cm,
                $reactforum,
                $discussion,
                $post,
                $user,
                $user,
                true
            );

        // Check the postdate matches our expectations.
        $this->assertEquals(userdate($expectation, "", \core_date::get_user_timezone($user)), $renderable->get_postdate());
    }
}
