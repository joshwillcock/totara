<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010, 2011 Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage dialogs
 */

/**
 * Program/category dialog generator
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/dialogs/dialog_content.class.php');
require_once($CFG->dirroot.'/local/program/lib.php');
require_once($CFG->libdir.'/datalib.php');

/**
 * Class for generating single select program dialog markup
 *
 * @access  public
 */
class totara_dialog_content_programs extends totara_dialog_content {

    /**
     * Current category (e.g., show children of this category)
     *
     * @access  public
     * @var     integer
     */
    public $categoryid;


    /**
     * Categories at this level (indexed by category ID)
     *
     * @access  public
     * @var     array
     */
    public $categories = array();


    /**
     * Courses at this level
     *
     * @access  public
     * @var     array
     */
    public $programs = array();



    /**
     * Set current category
     *
     * @see     totara_dialog_hierarchy::categoryid
     *
     * @access  public
     * @param   $categoryid     int     Category id
     */
    public function __construct($categoryid) {

        $this->categoryid = $categoryid;

        // If category ID doesn't equal 0, must be only loading the tree
        if ($this->categoryid > 0) {
            $this->show_treeview_only = true;
        }

        // Load child categories
        $this->load_categories();

        // Load child courses
        $this->load_programs();
    }


    /**
     * Load categories to display
     *
     * @access  public
     */
    public function load_categories() {

        // If category 0, make fake object
        if (!$this->categoryid) {
            $parent = new object();
            $parent->id = 0;
        }
        else {
            // Load category
            if (!$parent = get_record('course_categories', 'id', $this->categoryid)) {
                error('Category ID was incorrect');
            }
        }

        // Load child categories
        $categories = get_child_categories($parent->id);

        $category_ids = array();
        foreach ($categories as $cat) {
            $category_ids[] = $cat->id;
        }

        // Get item counts for categories
        $category_item_counts = (count($category_ids) > 0) ? get_category_item_count($category_ids, false) : array();

        // Fix array to be indexed by prefixed id's (so it doesn't conflict with course id's)
        foreach ($categories as $category) {
            $item_count = array_key_exists($category->id, $category_item_counts) ? $category_item_counts[$category->id] : 0;

            //Dont show category if there are no items in it
            if ($item_count > 0) {
                $c = new object();
                $c->id = 'cat'.$category->id;
                $c->fullname = $category->name;

                $this->categories[$c->id] = $c;
            }
        }

        // Also fill parents array
        $this->parent_items = $this->categories;

        // Make categories unselectable
        $this->disabled_items = $this->parent_items;
    }


    /**
     * Load programs to display
     *
     * @access  public
     */
    public function load_programs() {
        if ($this->categoryid) {
            $progs = prog_get_programs($this->categoryid, "fullname ASC", 'p.id, p.fullname, p.sortorder, p.visible');
            foreach ($progs as $key=>$prog) {
                $program = new program($prog->id);
                if (!$program->is_accessible() || $program->visible == 0) {
                    unset($progs[$key]);
                }
            }
            $this->programs = $progs;
        }
    }


    /**
     * Generate markup, but first merge categories and programs together
     *
     * @access  public
     * @return  string
     */
    public function generate_markup() {

        // Merge categories and program (programs to follow categories)
        $this->items = array_merge($this->categories, $this->programs);

        return parent::generate_markup();
    }
}