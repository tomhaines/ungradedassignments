<?php
/******************************************************************************
 *
 *  Name: Ungraded Assignment Block
 *  Author: Thomash Haines (thomash@cciu.org),
 *          Laura Mikowychok (laurami@cciu.org)
 *          from Chester County Intermediate Unit - www.cciu.org
 *
 *  Date: 06/10/2012
 *
 *  Description: This block finds and lists all assignmnts that have been modified
 *  since the last time it has been graded (a.k.a assignments needing grading)
 *
 *  It also finds quiz attempts that include a question requiring manual
 *  grading, that have not been graded  
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 ******************************************************************************/
class block_ungradedassignments extends block_base {
	public function init() {
		$this->title = get_string('ungradedassignments', 'block_ungradedassignments');
	}

	public function get_content() {
		global $CFG, $PAGE, $DB;

		if ( $this->content !== null) {
			return $this->content;
		}

	  require_once("../config.php");
		
		// get the course id
		$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
		
		// check if user has permission to grade
		if (has_capability('mod/assignment:grade', $PAGE->context)) {	
		
			

			// load some block configuration
			$blockPath = isset($this->config->blockdirectory) ? $this->config->blockdirectory : "/ungradedassignments";	
			$collapseImg = "{$CFG->wwwroot}/blocks{$blockPath}/img/collapse.png";
			$expandImg =  "{$CFG->wwwroot}/blocks{$blockPath}/img/expand.png";
			$refreshImg =  "{$CFG->wwwroot}/blocks{$blockPath}/img/refresh.png";
			
			$hideQuizzes = isset($this->config->hidequizzes) ? $this->config->hidequizzes : false;
			$descHideQuizzes = $hideQuizzes ? "<br/><br/><small><em>" . get_string("hidequizzestext", 'block_ungradedassignments') . "</em></small>" : "";
			
			//this will determine whether or not the list will be collapsed
			$condense = isset($this->config->condense) ? $this->config->condense : true;
			$collapseHTML = $condense ? "display:none;" : "";
			$defaultCollapseIcon = $condense ? $expandImg : $collapseImg;
			
			//whether or not we should show assignments from users no longer enrolled
			$showunenrolled = isset($this->config->showunenrolled) ? $this->config->showunenrolled : false;
			if ( $showunenrolled ) {
					$sqlEnrolledHTML1 = "";
					$sqlEnrolledHTML2 = "";
					$descUnenrolledText = "<br/><br/><small><em>Displaying assignments from users previously and currently enrolled in this course.</small></em>";
			} else {	
					$sqlEnrolledHTML1 = "INNER JOIN {$CFG->prefix}role_assignments r on (r.userid = u.id)";
					$sqlEnrolledHTML2 = "AND r.contextid = {$PAGE->context->id}";
					$descUnenrolledText = "";
			}
			
			/*	
				get all assignments where the time modified is greater than the 
				time marked. this will find all assigments in need of grading.
				then, exclude all assignments from users no longer enrolled
				in the course	
			*/
			$query ="SELECT FLOOR(RAND()* 100000) as  rand, s.id as subid, 'assignment' as assignmenttype, s.timemodified as timemodified, u.id as userid,  m.id as id ,a.name as name,u.lastname as lastname ,u.firstname as firstname
						FROM {$CFG->prefix}assignment a
						INNER JOIN {$CFG->prefix}course_modules m ON (a.id=m.instance AND a.course=m.course AND m.module=1) 
						INNER JOIN {$CFG->prefix}assignment_submissions s ON (a.id=s.assignment)
						INNER JOIN {$CFG->prefix}user u ON (u.id=s.userid)
						{$sqlEnrolledHTML1}
						WHERE a.course={$id}
						AND s.timemodified>s.timemarked
						{$sqlEnrolledHTML2}";
						
			$query = ($hideQuizzes) ? $query : $query .
						
						" UNION

						SELECT FLOOR(RAND()* 100000) as rand, qa.id as subid, 'quiz' as assignmenttype, qa.timemodified as timemodified, u.id as userid,  qa.quiz as id,q.name as name,u.lastname as lastname,u.firstname as firstname
						from {$CFG->prefix}quiz_attempts qa
						inner join {$CFG->prefix}user u on u.id = qa.userid
						inner join {$CFG->prefix}quiz q on qa.quiz = q.id
						{$sqlEnrolledHTML1}
						where qa.sumgrades is null
						and q.course=${id}
						and qa.timefinish > 0
						{$sqlEnrolledHTML2}";

			$query .=	" UNION
						
						select FLOOR(RAND()* 100000) as rand,  ge.id as subid, 'glossary' as assignmenttype, max(ge.timemodified), u.id as userid, ge.glossaryid as id, g.name, u.lastname, u.firstname
						from {$CFG->prefix}glossary_entries ge
						inner join {$CFG->prefix}user u on u.id = ge.userid
						inner join {$CFG->prefix}glossary g on ge.glossaryid = g.id
						{$sqlEnrolledHTML1}
						where ge.userid not in (
						    select userid from {$CFG->prefix}grade_grades gg
						    inner join {$CFG->prefix}grade_items gi on gi.id = gg.itemid
						    where gi.itemmodule = 'glossary' and gi.iteminstance = ge.glossaryid)
							and g.course = ${id}
						{$sqlEnrolledHTML2}
						group by userid, g.id
						";

						
			$query .=	" order by name, lastname, firstname";

			$assignments = $DB->get_records_sql($query);
			
			// create the content class
			$this->content = new stdClass;
			$this->content->text = '';
			
			$courses = array();
			
			$totalAssignments = 0;
			// loop through assignments and add them to the $userArray array
			foreach ($assignments as $assignment) { 
				$userArray = array();
				$userArray["userid"] = $assignment->userid;
				$userArray["name"] = $assignment->lastname . ', ' . $assignment->firstname;
				$userArray["timemodified"] = date("F j, Y, g:i a", $assignment->timemodified);
				$userArray["courseid"] = $assignment->id;
				$userArray["assignmenttype"] = $assignment->assignmenttype;
				$userArray["subid"] = $assignment->subid;
				$userArray["rand"] = $assignment->rand;
				
				$courses[$assignment->name][] = $userArray;
				
				// increment the number of assignments
				$totalAssignments += 1;
			}

			// loop through $assignments and build html to display them including javascript to allow collapsing\
			foreach ($courses as $key=>$value) {
				//javascript id to identify the div containing the submissions for this assignment
				$divID="mdl_block_ungraded_assignments_" . $value[0]["rand"];
				//javascript id to identify the collapse / expand images for this assignment
				$imgID="imgCollapse_block_ungraded_assignments_" . $value[0]["rand"];
				
				//display the assignment
				$this->content->text .= "<div style=\"padding: 2px; cursor:pointer;\" onClick=\"document.getElementById('$imgID').src=(document.getElementById('$imgID').src=='$collapseImg') ? document.getElementById('$imgID').src='$expandImg' : document.getElementById('$imgID').src='$collapseImg';document.getElementById('$divID').style.display=(document.getElementById('$divID').style.display=='none') ? document.getElementById('$divID').style.display='block' : document.getElementById('$divID').style.display='none'; \"><img src=\"$defaultCollapseIcon\" id=\"$imgID\" target=\"_blank\" style=\"padding-right:2px;\" alt=\"\" />$key</div>
				 	<small><ul id=\"$divID\"  style=\"margin-top:0px;padding-left: 10px; $collapseHTML\">";
				
				
				
				foreach ($value as $userInfo) {
					// display the submissions needing graded
					switch ($userInfo["assignmenttype"]) {
						case ("quiz") : {
							$gradeURL = $CFG->wwwroot . "/mod/quiz/review.php?q=" . $userInfo["courseid"] . "&attempt=" . $userInfo["subid"];
							$iconURL = $CFG->wwwroot . "/theme/image.php?image=icon&component=quiz";
							break;
						}
						case ("assignment") : {
							$gradeURL = $CFG->wwwroot . "/mod/assignment/submissions.php?id=" . $userInfo["courseid"] . "&userid=" . $userInfo["userid"] . "&mode=single&offset=1";
							$iconURL = $CFG->wwwroot . "/theme/image.php?image=icon&component=assignment";
							break;
						}
						case ("glossary") : {
							$gradeURL = $CFG->wwwroot . "/grade/report/grader/index.php?id=". $id;
							$iconURL = $CFG->wwwroot . "/theme/image.php?image=icon&component=glossary";
							break;
						}
					}
					$this->content->text .=
						"<li style=\"margin:3px;border: 1px dotted; list-style: none;\"><a href=\"{$gradeURL}\" target=\"_blank\" title=\"" . get_string('clicktograde', 'block_ungradedassignments') . " " . $userInfo["assignmenttype"] . "\"><img src=\"{$iconURL}\" class=\"icon\" alt=\"Grade Icon\" /></a><strong><a href=\"{$CFG->wwwroot}/user/view.php?id=" . $userInfo["userid"] . "&course=$id \" target=\"_blank\">" . $userInfo["name"] . "</a></strong>" . 
						"<br/><em><div style=\"padding-left: 20px;\">" . $userInfo["timemodified"] . "</div></em>" . 
						"</li>";
				}
				$this->content->text .="</ul></small>";
			}
			$this->content->footer = "<small>" . get_string('totalwork', 'block_ungradedassignments') . "<strong>{$totalAssignments}</strong></small>$descUnenrolledText$descHideQuizzes<br/>";
			$this->content->footer .= "<a href=\"". $_SERVER['REQUEST_URI'] . "\"><img src=\"$refreshImg\" /></a>";
			return $this->content; 
		}
		
	}

	

	
	function applicable_formats() {
		// this block should only be shown in courses
		return array('site-index' => false,
					'course-view' => true, 'course-view-social' => true,
					'mod' => false, 'mod-quiz' => false);
	}
	
	function instance_allow_config() {
		return true;
	}
}
?>
