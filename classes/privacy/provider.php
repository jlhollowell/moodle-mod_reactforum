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
 * Privacy Subsystem implementation for mod_reactforum.
 *
 * @package    mod_reactforum
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reactforum\privacy;

use \core_privacy\local\request\userlist;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\local\request\deletion_criteria;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\helper as request_helper;
use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\transform;

defined('MOODLE_INTERNAL') || die();

/**
 * Implementation of the privacy subsystem plugin provider for the reactforum activity module.
 *
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin\provider interface.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // This plugin has some sitewide user preferences to export.
    \core_privacy\local\request\user_preference_provider
{

    use subcontext_info;

    /**
     * Returns meta data about this system.
     *
     * @param   collection     $items The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $items) : collection {
        // The 'reactforum' table does not store any specific user data.
        $items->add_database_table('reactforum_digests', [
            'reactforum' => 'privacy:metadata:reactforum_digests:reactforum',
            'userid' => 'privacy:metadata:reactforum_digests:userid',
            'maildigest' => 'privacy:metadata:reactforum_digests:maildigest',
        ], 'privacy:metadata:reactforum_digests');

        // The 'reactforum_discussions' table stores the metadata about each reactforum discussion.
        $items->add_database_table('reactforum_discussions', [
            'name' => 'privacy:metadata:reactforum_discussions:name',
            'userid' => 'privacy:metadata:reactforum_discussions:userid',
            'assessed' => 'privacy:metadata:reactforum_discussions:assessed',
            'timemodified' => 'privacy:metadata:reactforum_discussions:timemodified',
            'usermodified' => 'privacy:metadata:reactforum_discussions:usermodified',
        ], 'privacy:metadata:reactforum_discussions');

        // The 'reactforum_discussion_subs' table stores information about which discussions a user is subscribed to.
        $items->add_database_table('reactforum_discussion_subs', [
            'discussionid' => 'privacy:metadata:reactforum_discussion_subs:discussionid',
            'preference' => 'privacy:metadata:reactforum_discussion_subs:preference',
            'userid' => 'privacy:metadata:reactforum_discussion_subs:userid',
        ], 'privacy:metadata:reactforum_discussion_subs');

        // The 'reactforum_posts' table stores the metadata about each reactforum discussion.
        $items->add_database_table('reactforum_posts', [
            'discussion' => 'privacy:metadata:reactforum_posts:discussion',
            'parent' => 'privacy:metadata:reactforum_posts:parent',
            'created' => 'privacy:metadata:reactforum_posts:created',
            'modified' => 'privacy:metadata:reactforum_posts:modified',
            'subject' => 'privacy:metadata:reactforum_posts:subject',
            'message' => 'privacy:metadata:reactforum_posts:message',
            'userid' => 'privacy:metadata:reactforum_posts:userid',
        ], 'privacy:metadata:reactforum_posts');

        // The 'reactforum_queue' table contains user data, but it is only a temporary cache of other data.
        // We should not need to export it as it does not allow profiling of a user.

        // The 'reactforum_read' table stores data about which reactforum posts have been read by each user.
        $items->add_database_table('reactforum_read', [
            'userid' => 'privacy:metadata:reactforum_read:userid',
            'discussionid' => 'privacy:metadata:reactforum_read:discussionid',
            'postid' => 'privacy:metadata:reactforum_read:postid',
            'firstread' => 'privacy:metadata:reactforum_read:firstread',
            'lastread' => 'privacy:metadata:reactforum_read:lastread',
        ], 'privacy:metadata:reactforum_read');

        // The 'reactforum_subscriptions' table stores information about which reactforums a user is subscribed to.
        $items->add_database_table('reactforum_subscriptions', [
            'userid' => 'privacy:metadata:reactforum_subscriptions:userid',
            'reactforum' => 'privacy:metadata:reactforum_subscriptions:reactforum',
        ], 'privacy:metadata:reactforum_subscriptions');

        // The 'reactforum_subscriptions' table stores information about which reactforums a user is subscribed to.
        $items->add_database_table('reactforum_track_prefs', [
            'userid' => 'privacy:metadata:reactforum_track_prefs:userid',
            'reactforumid' => 'privacy:metadata:reactforum_track_prefs:reactforumid',
        ], 'privacy:metadata:reactforum_track_prefs');

        // The 'reactforum_queue' table stores temporary data that is not exported/deleted.
        $items->add_database_table('reactforum_queue', [
            'userid' => 'privacy:metadata:reactforum_queue:userid',
            'discussionid' => 'privacy:metadata:reactforum_queue:discussionid',
            'postid' => 'privacy:metadata:reactforum_queue:postid',
            'timemodified' => 'privacy:metadata:reactforum_queue:timemodified'
        ], 'privacy:metadata:reactforum_queue');

        // ReactForum posts can be tagged and rated.
        $items->link_subsystem('core_tag', 'privacy:metadata:core_tag');
        $items->link_subsystem('core_rating', 'privacy:metadata:core_rating');

        // There are several user preferences.
        $items->add_user_preference('maildigest', 'privacy:metadata:preference:maildigest');
        $items->add_user_preference('autosubscribe', 'privacy:metadata:preference:autosubscribe');
        $items->add_user_preference('trackreactforums', 'privacy:metadata:preference:trackreactforums');
        $items->add_user_preference('markasreadonnotification', 'privacy:metadata:preference:markasreadonnotification');

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * In the case of reactforum, that is any reactforum where the user has made any post, rated any content, or has any preferences.
     *
     * @param   int         $userid     The user to search.
     * @return  contextlist $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : \core_privacy\local\request\contextlist {
        $ratingsql = \core_rating\privacy\provider::get_sql_join('rat', 'mod_reactforum', 'post', 'p.id', $userid);
        // Fetch all reactforum discussions, and reactforum posts.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {reactforum} f ON f.id = cm.instance
             LEFT JOIN {reactforum_discussions} d ON d.reactforum = f.id
             LEFT JOIN {reactforum_posts} p ON p.discussion = d.id
             LEFT JOIN {reactforum_digests} dig ON dig.reactforum = f.id AND dig.userid = :digestuserid
             LEFT JOIN {reactforum_subscriptions} sub ON sub.reactforum = f.id AND sub.userid = :subuserid
             LEFT JOIN {reactforum_track_prefs} pref ON pref.reactforumid = f.id AND pref.userid = :prefuserid
             LEFT JOIN {reactforum_read} hasread ON hasread.reactforumid = f.id AND hasread.userid = :hasreaduserid
             LEFT JOIN {reactforum_discussion_subs} dsub ON dsub.reactforum = f.id AND dsub.userid = :dsubuserid
             {$ratingsql->join}
                 WHERE (
                    p.userid        = :postuserid OR
                    d.userid        = :discussionuserid OR
                    dig.id IS NOT NULL OR
                    sub.id IS NOT NULL OR
                    pref.id IS NOT NULL OR
                    hasread.id IS NOT NULL OR
                    dsub.id IS NOT NULL OR
                    {$ratingsql->userwhere}
                )
        ";
        $params = [
            'modname'           => 'reactforum',
            'contextlevel'      => CONTEXT_MODULE,
            'postuserid'        => $userid,
            'discussionuserid'  => $userid,
            'digestuserid'      => $userid,
            'subuserid'         => $userid,
            'prefuserid'        => $userid,
            'hasreaduserid'     => $userid,
            'dsubuserid'        => $userid,
        ];
        $params += $ratingsql->params;

        $contextlist = new \core_privacy\local\request\contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $params = [
            'instanceid'    => $context->instanceid,
            'modulename'    => 'reactforum',
        ];

        // Discussion authors.
        $sql = "SELECT d.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {reactforum} f ON f.id = cm.instance
                  JOIN {reactforum_discussions} d ON d.reactforum = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // ReactForum authors.
        $sql = "SELECT p.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {reactforum} f ON f.id = cm.instance
                  JOIN {reactforum_discussions} d ON d.reactforum = f.id
                  JOIN {reactforum_posts} p ON d.id = p.discussion
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // ReactForum post ratings.
        $sql = "SELECT p.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {reactforum} f ON f.id = cm.instance
                  JOIN {reactforum_discussions} d ON d.reactforum = f.id
                  JOIN {reactforum_posts} p ON d.id = p.discussion
                 WHERE cm.id = :instanceid";
        \core_rating\privacy\provider::get_users_in_context_from_sql($userlist, 'rat', 'mod_reactforum', 'post', $sql, $params);

        // ReactForum Digest settings.
        $sql = "SELECT dig.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {reactforum} f ON f.id = cm.instance
                  JOIN {reactforum_digests} dig ON dig.reactforum = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // ReactForum Subscriptions.
        $sql = "SELECT sub.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {reactforum} f ON f.id = cm.instance
                  JOIN {reactforum_subscriptions} sub ON sub.reactforum = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Discussion subscriptions.
        $sql = "SELECT dsub.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {reactforum} f ON f.id = cm.instance
                  JOIN {reactforum_discussion_subs} dsub ON dsub.reactforum = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Read Posts.
        $sql = "SELECT hasread.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {reactforum} f ON f.id = cm.instance
                  JOIN {reactforum_read} hasread ON hasread.reactforumid = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Tracking Preferences.
        $sql = "SELECT pref.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {reactforum} f ON f.id = cm.instance
                  JOIN {reactforum_track_prefs} pref ON pref.reactforumid = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Store all user preferences for the plugin.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $user = \core_user::get_user($userid);

        switch ($user->maildigest) {
            case 1:
                $digestdescription = get_string('emaildigestcomplete');
                break;
            case 2:
                $digestdescription = get_string('emaildigestsubjects');
                break;
            case 0:
            default:
                $digestdescription = get_string('emaildigestoff');
                break;
        }
        writer::export_user_preference('mod_reactforum', 'maildigest', $user->maildigest, $digestdescription);

        switch ($user->autosubscribe) {
            case 0:
                $subscribedescription = get_string('autosubscribeno');
                break;
            case 1:
            default:
                $subscribedescription = get_string('autosubscribeyes');
                break;
        }
        writer::export_user_preference('mod_reactforum', 'autosubscribe', $user->autosubscribe, $subscribedescription);

        switch ($user->trackreactforums) {
            case 0:
                $trackreactforumdescription = get_string('trackreactforumsno');
                break;
            case 1:
            default:
                $trackreactforumdescription = get_string('trackreactforumsyes');
                break;
        }
        writer::export_user_preference('mod_reactforum', 'trackreactforums', $user->trackreactforums, $trackreactforumdescription);

        $markasreadonnotification = get_user_preferences('markasreadonnotification', null, $user->id);
        if (null !== $markasreadonnotification) {
            switch ($markasreadonnotification) {
                case 0:
                    $markasreadonnotificationdescription = get_string('markasreadonnotificationno', 'mod_reactforum');
                    break;
                case 1:
                default:
                    $markasreadonnotificationdescription = get_string('markasreadonnotificationyes', 'mod_reactforum');
                    break;
            }
            writer::export_user_preference('mod_reactforum', 'markasreadonnotification', $markasreadonnotification,
                    $markasreadonnotificationdescription);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    c.id AS contextid,
                    f.*,
                    cm.id AS cmid,
                    dig.maildigest,
                    sub.userid AS subscribed,
                    pref.userid AS tracked
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {reactforum} f ON f.id = cm.instance
             LEFT JOIN {reactforum_digests} dig ON dig.reactforum = f.id AND dig.userid = :digestuserid
             LEFT JOIN {reactforum_subscriptions} sub ON sub.reactforum = f.id AND sub.userid = :subuserid
             LEFT JOIN {reactforum_track_prefs} pref ON pref.reactforumid = f.id AND pref.userid = :prefuserid
                 WHERE (
                    c.id {$contextsql}
                )
        ";

        $params = [
            'digestuserid'  => $userid,
            'subuserid'     => $userid,
            'prefuserid'    => $userid,
        ];
        $params += $contextparams;

        // Keep a mapping of reactforumid to contextid.
        $mappings = [];

        $reactforums = $DB->get_recordset_sql($sql, $params);
        foreach ($reactforums as $reactforum) {
            $mappings[$reactforum->id] = $reactforum->contextid;

            $context = \context::instance_by_id($mappings[$reactforum->id]);

            // Store the main reactforum data.
            $data = request_helper::get_context_data($context, $user);
            writer::with_context($context)
                ->export_data([], $data);
            request_helper::export_context_files($context, $user);

            // Store relevant metadata about this reactforum instance.
            static::export_digest_data($userid, $reactforum);
            static::export_subscription_data($userid, $reactforum);
            static::export_tracking_data($userid, $reactforum);
        }
        $reactforums->close();

        if (!empty($mappings)) {
            // Store all discussion data for this reactforum.
            static::export_discussion_data($userid, $mappings);

            // Store all post data for this reactforum.
            static::export_all_posts($userid, $mappings);
        }
    }

    /**
     * Store all information about all discussions that we have detected this user to have access to.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   array       $mappings A list of mappings from reactforumid => contextid.
     * @return  array       Which reactforums had data written for them.
     */
    protected static function export_discussion_data(int $userid, array $mappings) {
        global $DB;

        // Find all of the discussions, and discussion subscriptions for this reactforum.
        list($reactforuminsql, $reactforumparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);
        $sql = "SELECT
                    d.*,
                    g.name as groupname,
                    dsub.preference
                  FROM {reactforum} f
                  JOIN {reactforum_discussions} d ON d.reactforum = f.id
             LEFT JOIN {groups} g ON g.id = d.groupid
             LEFT JOIN {reactforum_discussion_subs} dsub ON dsub.discussion = d.id AND dsub.userid = :dsubuserid
             LEFT JOIN {reactforum_posts} p ON p.discussion = d.id
                 WHERE f.id ${reactforuminsql}
                   AND (
                        d.userid    = :discussionuserid OR
                        p.userid    = :postuserid OR
                        dsub.id IS NOT NULL
                   )
        ";

        $params = [
            'postuserid'        => $userid,
            'discussionuserid'  => $userid,
            'dsubuserid'        => $userid,
        ];
        $params += $reactforumparams;

        // Keep track of the reactforums which have data.
        $reactforumswithdata = [];

        $discussions = $DB->get_recordset_sql($sql, $params);
        foreach ($discussions as $discussion) {
            // No need to take timestart into account as the user has some involvement already.
            // Ignore discussion timeend as it should not block access to user data.
            $reactforumswithdata[$discussion->reactforum] = true;
            $context = \context::instance_by_id($mappings[$discussion->reactforum]);

            // Store related metadata for this discussion.
            static::export_discussion_subscription_data($userid, $context, $discussion);

            $discussiondata = (object) [
                'name' => format_string($discussion->name, true),
                'pinned' => transform::yesno((bool) $discussion->pinned),
                'timemodified' => transform::datetime($discussion->timemodified),
                'usermodified' => transform::datetime($discussion->usermodified),
                'creator_was_you' => transform::yesno($discussion->userid == $userid),
            ];

            // Store the discussion content.
            writer::with_context($context)
                ->export_data(static::get_discussion_area($discussion), $discussiondata);

            // ReactForum discussions do not have any files associately directly with them.
        }

        $discussions->close();

        return $reactforumswithdata;
    }

    /**
     * Store all information about all posts that we have detected this user to have access to.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   array       $mappings A list of mappings from reactforumid => contextid.
     * @return  array       Which reactforums had data written for them.
     */
    protected static function export_all_posts(int $userid, array $mappings) {
        global $DB;

        // Find all of the posts, and post subscriptions for this reactforum.
        list($reactforuminsql, $reactforumparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);
        $ratingsql = \core_rating\privacy\provider::get_sql_join('rat', 'mod_reactforum', 'post', 'p.id', $userid);
        $sql = "SELECT
                    p.discussion AS id,
                    f.id AS reactforumid,
                    d.name,
                    d.groupid
                  FROM {reactforum} f
                  JOIN {reactforum_discussions} d ON d.reactforum = f.id
                  JOIN {reactforum_posts} p ON p.discussion = d.id
             LEFT JOIN {reactforum_read} fr ON fr.postid = p.id AND fr.userid = :readuserid
            {$ratingsql->join}
                 WHERE f.id ${reactforuminsql} AND
                (
                    p.userid = :postuserid OR
                    fr.id IS NOT NULL OR
                    {$ratingsql->userwhere}
                )
              GROUP BY f.id, p.discussion, d.name, d.groupid
        ";

        $params = [
            'postuserid'    => $userid,
            'readuserid'    => $userid,
        ];
        $params += $reactforumparams;
        $params += $ratingsql->params;

        $discussions = $DB->get_records_sql($sql, $params);
        foreach ($discussions as $discussion) {
            $context = \context::instance_by_id($mappings[$discussion->reactforumid]);
            static::export_all_posts_in_discussion($userid, $context, $discussion);
        }
    }

    /**
     * Store all information about all posts that we have detected this user to have access to.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \context    $context The instance of the reactforum context.
     * @param   \stdClass   $discussion The discussion whose data is being exported.
     */
    protected static function export_all_posts_in_discussion(int $userid, \context $context, \stdClass $discussion) {
        global $DB, $USER;

        $discussionid = $discussion->id;

        // Find all of the posts, and post subscriptions for this reactforum.
        $ratingsql = \core_rating\privacy\provider::get_sql_join('rat', 'mod_reactforum', 'post', 'p.id', $userid);
        $sql = "SELECT
                    p.*,
                    d.reactforum AS reactforumid,
                    fr.firstread,
                    fr.lastread,
                    fr.id AS readflag,
                    rat.id AS hasratings
                    FROM {reactforum_discussions} d
                    JOIN {reactforum_posts} p ON p.discussion = d.id
               LEFT JOIN {reactforum_read} fr ON fr.postid = p.id AND fr.userid = :readuserid
            {$ratingsql->join} AND {$ratingsql->userwhere}
                   WHERE d.id = :discussionid
        ";

        $params = [
            'discussionid'  => $discussionid,
            'readuserid'    => $userid,
        ];
        $params += $ratingsql->params;

        // Keep track of the reactforums which have data.
        $structure = (object) [
            'children' => [],
        ];

        $posts = $DB->get_records_sql($sql, $params);
        foreach ($posts as $post) {
            $post->hasdata = (isset($post->hasdata)) ? $post->hasdata : false;
            $post->hasdata = $post->hasdata || !empty($post->hasratings);
            $post->hasdata = $post->hasdata || $post->readflag;
            $post->hasdata = $post->hasdata || ($post->userid == $USER->id);

            if (0 == $post->parent) {
                $structure->children[$post->id] = $post;
            } else {
                if (empty($posts[$post->parent]->children)) {
                    $posts[$post->parent]->children = [];
                }
                $posts[$post->parent]->children[$post->id] = $post;
            }

            // Set all parents.
            if ($post->hasdata) {
                $curpost = $post;
                while ($curpost->parent != 0) {
                    $curpost = $posts[$curpost->parent];
                    $curpost->hasdata = true;
                }
            }
        }

        $discussionarea = static::get_discussion_area($discussion);
        $discussionarea[] = get_string('posts', 'mod_reactforum');
        static::export_posts_in_structure($userid, $context, $discussionarea, $structure);
    }

    /**
     * Export all posts in the provided structure.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \context    $context The instance of the reactforum context.
     * @param   array       $parentarea The subcontext of the parent.
     * @param   \stdClass   $structure The post structure and all of its children
     */
    protected static function export_posts_in_structure(int $userid, \context $context, $parentarea, \stdClass $structure) {
        foreach ($structure->children as $post) {
            if (!$post->hasdata) {
                // This tree has no content belonging to the user. Skip it and all children.
                continue;
            }

            $postarea = array_merge($parentarea, static::get_post_area($post));

            // Store the post content.
            static::export_post_data($userid, $context, $postarea, $post);

            if (isset($post->children)) {
                // Now export children of this post.
                static::export_posts_in_structure($userid, $context, $postarea, $post);
            }
        }
    }

    /**
     * Export all data in the post.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \context    $context The instance of the reactforum context.
     * @param   array       $postarea The subcontext of the parent.
     * @param   \stdClass   $post The post structure and all of its children
     */
    protected static function export_post_data(int $userid, \context $context, $postarea, $post) {
        // Store related metadata.
        static::export_read_data($userid, $context, $postarea, $post);

        $postdata = (object) [
            'subject' => format_string($post->subject, true),
            'created' => transform::datetime($post->created),
            'modified' => transform::datetime($post->modified),
            'author_was_you' => transform::yesno($post->userid == $userid),
        ];

        $postdata->message = writer::with_context($context)
            ->rewrite_pluginfile_urls($postarea, 'mod_reactforum', 'post', $post->id, $post->message);

        $postdata->message = format_text($postdata->message, $post->messageformat, (object) [
            'para'    => false,
            'trusted' => $post->messagetrust,
            'context' => $context,
        ]);

        writer::with_context($context)
            // Store the post.
            ->export_data($postarea, $postdata)

            // Store the associated files.
            ->export_area_files($postarea, 'mod_reactforum', 'post', $post->id);

        if ($post->userid == $userid) {
            // Store all ratings against this post as the post belongs to the user. All ratings on it are ratings of their content.
            \core_rating\privacy\provider::export_area_ratings($userid, $context, $postarea, 'mod_reactforum', 'post', $post->id, false);

            // Store all tags against this post as the tag belongs to the user.
            \core_tag\privacy\provider::export_item_tags($userid, $context, $postarea, 'mod_reactforum', 'reactforum_posts', $post->id);

            // Export all user data stored for this post from the plagiarism API.
            $coursecontext = $context->get_course_context();
            \core_plagiarism\privacy\provider::export_plagiarism_user_data($userid, $context, $postarea, [
                    'cmid' => $context->instanceid,
                    'course' => $coursecontext->instanceid,
                    'reactforum' => $post->reactforumid,
                    'discussionid' => $post->discussion,
                    'postid' => $post->id,
                ]);
        }

        // Check for any ratings that the user has made on this post.
        \core_rating\privacy\provider::export_area_ratings($userid,
                $context,
                $postarea,
                'mod_reactforum',
                'post',
                $post->id,
                $userid,
                true
            );
    }

    /**
     * Store data about daily digest preferences
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \stdClass   $reactforum The reactforum whose data is being exported.
     * @return  bool        Whether any data was stored.
     */
    protected static function export_digest_data(int $userid, \stdClass $reactforum) {
        if (null !== $reactforum->maildigest) {
            // The user has a specific maildigest preference for this reactforum.
            $a = (object) [
                'reactforum' => format_string($reactforum->name, true),
            ];

            switch ($reactforum->maildigest) {
                case 0:
                    $a->type = get_string('emaildigestoffshort', 'mod_reactforum');
                    break;
                case 1:
                    $a->type = get_string('emaildigestcompleteshort', 'mod_reactforum');
                    break;
                case 2:
                    $a->type = get_string('emaildigestsubjectsshort', 'mod_reactforum');
                    break;
            }

            writer::with_context(\context_module::instance($reactforum->cmid))
                ->export_metadata([], 'digestpreference', $reactforum->maildigest,
                    get_string('privacy:digesttypepreference', 'mod_reactforum', $a));

            return true;
        }

        return false;
    }

    /**
     * Store data about whether the user subscribes to reactforum.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \stdClass   $reactforum The reactforum whose data is being exported.
     * @return  bool        Whether any data was stored.
     */
    protected static function export_subscription_data(int $userid, \stdClass $reactforum) {
        if (null !== $reactforum->subscribed) {
            // The user is subscribed to this reactforum.
            writer::with_context(\context_module::instance($reactforum->cmid))
                ->export_metadata([], 'subscriptionpreference', 1, get_string('privacy:subscribedtoreactforum', 'mod_reactforum'));

            return true;
        }

        return false;
    }

    /**
     * Store data about whether the user subscribes to this particular discussion.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \context_module $context The instance of the reactforum context.
     * @param   \stdClass   $discussion The discussion whose data is being exported.
     * @return  bool        Whether any data was stored.
     */
    protected static function export_discussion_subscription_data(int $userid, \context_module $context, \stdClass $discussion) {
        $area = static::get_discussion_area($discussion);
        if (null !== $discussion->preference) {
            // The user has a specific subscription preference for this discussion.
            $a = (object) [];

            switch ($discussion->preference) {
                case \mod_reactforum\subscriptions::REACTFORUM_DISCUSSION_UNSUBSCRIBED:
                    $a->preference = get_string('unsubscribed', 'mod_reactforum');
                    break;
                default:
                    $a->preference = get_string('subscribed', 'mod_reactforum');
                    break;
            }

            writer::with_context($context)
                ->export_metadata(
                    $area,
                    'subscriptionpreference',
                    $discussion->preference,
                    get_string('privacy:discussionsubscriptionpreference', 'mod_reactforum', $a)
                );

            return true;
        }

        return true;
    }

    /**
     * Store reactforum read-tracking data about a particular reactforum.
     *
     * This is whether a reactforum has read-tracking enabled or not.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \stdClass   $reactforum The reactforum whose data is being exported.
     * @return  bool        Whether any data was stored.
     */
    protected static function export_tracking_data(int $userid, \stdClass $reactforum) {
        if (null !== $reactforum->tracked) {
            // The user has a main preference to track all reactforums, but has opted out of this one.
            writer::with_context(\context_module::instance($reactforum->cmid))
                ->export_metadata([], 'trackreadpreference', 0, get_string('privacy:readtrackingdisabled', 'mod_reactforum'));

            return true;
        }

        return false;
    }

    /**
     * Store read-tracking information about a particular reactforum post.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \context_module $context The instance of the reactforum context.
     * @param   array       $postarea The subcontext for this post.
     * @param   \stdClass   $post The post whose data is being exported.
     * @return  bool        Whether any data was stored.
     */
    protected static function export_read_data(int $userid, \context_module $context, array $postarea, \stdClass $post) {
        if (null !== $post->firstread) {
            $a = (object) [
                'firstread' => $post->firstread,
                'lastread'  => $post->lastread,
            ];

            writer::with_context($context)
                ->export_metadata(
                    $postarea,
                    'postread',
                    (object) [
                        'firstread' => $post->firstread,
                        'lastread' => $post->lastread,
                    ],
                    get_string('privacy:postwasread', 'mod_reactforum', $a)
                );

            return true;
        }

        return false;
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context                 $context   The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // Check that this is a context_module.
        if (!$context instanceof \context_module) {
            return;
        }

        // Get the course module.
        if (!$cm = get_coursemodule_from_id('reactforum', $context->instanceid)) {
            return;
        }

        $reactforumid = $cm->instance;

        $DB->delete_records('reactforum_track_prefs', ['reactforumid' => $reactforumid]);
        $DB->delete_records('reactforum_subscriptions', ['reactforum' => $reactforumid]);
        $DB->delete_records('reactforum_read', ['reactforumid' => $reactforumid]);
        $DB->delete_records('reactforum_digests', ['reactforum' => $reactforumid]);

        // Delete all discussion items.
        $DB->delete_records_select(
            'reactforum_queue',
            "discussionid IN (SELECT id FROM {reactforum_discussions} WHERE reactforum = :reactforum)",
            [
                'reactforum' => $reactforumid,
            ]
        );

        $DB->delete_records_select(
            'reactforum_posts',
            "discussion IN (SELECT id FROM {reactforum_discussions} WHERE reactforum = :reactforum)",
            [
                'reactforum' => $reactforumid,
            ]
        );

        $DB->delete_records('reactforum_discussion_subs', ['reactforum' => $reactforumid]);
        $DB->delete_records('reactforum_discussions', ['reactforum' => $reactforumid]);

        // Delete all files from the posts.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_reactforum', 'post');
        $fs->delete_area_files($context->id, 'mod_reactforum', 'attachment');

        // Delete all ratings in the context.
        \core_rating\privacy\provider::delete_ratings($context, 'mod_reactforum', 'post');

        // Delete all Tags.
        \core_tag\privacy\provider::delete_item_tags($context, 'mod_reactforum', 'reactforum_posts');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $userid = $user->id;
        foreach ($contextlist as $context) {
            // Get the course module.
            $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
            $reactforum = $DB->get_record('reactforum', ['id' => $cm->instance]);

            $DB->delete_records('reactforum_track_prefs', [
                'reactforumid' => $reactforum->id,
                'userid' => $userid,
            ]);
            $DB->delete_records('reactforum_subscriptions', [
                'reactforum' => $reactforum->id,
                'userid' => $userid,
            ]);
            $DB->delete_records('reactforum_read', [
                'reactforumid' => $reactforum->id,
                'userid' => $userid,
            ]);

            $DB->delete_records('reactforum_digests', [
                'reactforum' => $reactforum->id,
                'userid' => $userid,
            ]);

            // Delete all discussion items.
            $DB->delete_records_select(
                'reactforum_queue',
                "userid = :userid AND discussionid IN (SELECT id FROM {reactforum_discussions} WHERE reactforum = :reactforum)",
                [
                    'userid' => $userid,
                    'reactforum' => $reactforum->id,
                ]
            );

            $DB->delete_records('reactforum_discussion_subs', [
                'reactforum' => $reactforum->id,
                'userid' => $userid,
            ]);

            // Do not delete discussion or reactforum posts.
            // Instead update them to reflect that the content has been deleted.
            $postsql = "userid = :userid AND discussion IN (SELECT id FROM {reactforum_discussions} WHERE reactforum = :reactforum)";
            $postidsql = "SELECT fp.id FROM {reactforum_posts} fp WHERE {$postsql}";
            $postparams = [
                'reactforum' => $reactforum->id,
                'userid' => $userid,
            ];

            // Update the subject.
            $DB->set_field_select('reactforum_posts', 'subject', '', $postsql, $postparams);

            // Update the message and its format.
            $DB->set_field_select('reactforum_posts', 'message', '', $postsql, $postparams);
            $DB->set_field_select('reactforum_posts', 'messageformat', FORMAT_PLAIN, $postsql, $postparams);

            // Mark the post as deleted.
            $DB->set_field_select('reactforum_posts', 'deleted', 1, $postsql, $postparams);

            // Note: Do _not_ delete ratings of other users. Only delete ratings on the users own posts.
            // Ratings are aggregate fields and deleting the rating of this post will have an effect on the rating
            // of any post.
            \core_rating\privacy\provider::delete_ratings_select($context, 'mod_reactforum', 'post',
                    "IN ($postidsql)", $postparams);

            // Delete all Tags.
            \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_reactforum', 'reactforum_posts',
                    "IN ($postidsql)", $postparams);

            // Delete all files from the posts.
            $fs = get_file_storage();
            $fs->delete_area_files_select($context->id, 'mod_reactforum', 'post', "IN ($postidsql)", $postparams);
            $fs->delete_area_files_select($context->id, 'mod_reactforum', 'attachment', "IN ($postidsql)", $postparams);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        $reactforum = $DB->get_record('reactforum', ['id' => $cm->instance]);

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['reactforumid' => $reactforum->id], $userinparams);

        $DB->delete_records_select('reactforum_track_prefs', "reactforumid = :reactforumid AND userid {$userinsql}", $params);
        $DB->delete_records_select('reactforum_subscriptions', "reactforum = :reactforumid AND userid {$userinsql}", $params);
        $DB->delete_records_select('reactforum_read', "reactforumid = :reactforumid AND userid {$userinsql}", $params);
        $DB->delete_records_select(
            'reactforum_queue',
            "userid {$userinsql} AND discussionid IN (SELECT id FROM {reactforum_discussions} WHERE reactforum = :reactforumid)",
            $params
        );
        $DB->delete_records_select('reactforum_discussion_subs', "reactforum = :reactforumid AND userid {$userinsql}", $params);

        // Do not delete discussion or reactforum posts.
        // Instead update them to reflect that the content has been deleted.
        $postsql = "userid {$userinsql} AND discussion IN (SELECT id FROM {reactforum_discussions} WHERE reactforum = :reactforumid)";
        $postidsql = "SELECT fp.id FROM {reactforum_posts} fp WHERE {$postsql}";

        // Update the subject.
        $DB->set_field_select('reactforum_posts', 'subject', '', $postsql, $params);

        // Update the subject and its format.
        $DB->set_field_select('reactforum_posts', 'message', '', $postsql, $params);
        $DB->set_field_select('reactforum_posts', 'messageformat', FORMAT_PLAIN, $postsql, $params);

        // Mark the post as deleted.
        $DB->set_field_select('reactforum_posts', 'deleted', 1, $postsql, $params);

        // Note: Do _not_ delete ratings of other users. Only delete ratings on the users own posts.
        // Ratings are aggregate fields and deleting the rating of this post will have an effect on the rating
        // of any post.
        \core_rating\privacy\provider::delete_ratings_select($context, 'mod_reactforum', 'post', "IN ($postidsql)", $params);

        // Delete all Tags.
        \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_reactforum', 'reactforum_posts', "IN ($postidsql)", $params);

        // Delete all files from the posts.
        $fs = get_file_storage();
        $fs->delete_area_files_select($context->id, 'mod_reactforum', 'post', "IN ($postidsql)", $params);
        $fs->delete_area_files_select($context->id, 'mod_reactforum', 'attachment', "IN ($postidsql)", $params);
    }
}
