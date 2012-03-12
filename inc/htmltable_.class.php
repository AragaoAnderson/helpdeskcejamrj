<?php
/*
 * @version $Id: HEADER 15930 2011-10-25 10:47:55Z jmd $
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2012 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Damien Touraine
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


/// HTMLTable class
/// Create a smart HTML table. The table allows cells to depend on other ones. As such, it is
/// possible to have rowspan for cells that are "father" of other ones. If a "father" has several
/// sons, then, it "rowspans" on all.
/// The table integrates the notion of group of rows (HTMLTable_Group). For instance, for
/// Computer_Device, each group represents a kind of device (network card, graphique card,
/// processor, memory, ...).
/// There is HTMLTable_SuperHeader that defines global headers for all groups. Each group cat cut
/// these HTMLTable_SuperHeader as as many HTMLTable_SubHeader as necessary. There is an automatic
/// organisation of the headers between groups.
///
/// The (strict) order of definition of the table is:
///    * Define all HTMLTable_SuperHeader that are used by each group: HTMLTable_::addHeader()
///    * Define one HTMLTable_Group: HTMLTable_::createGroup()
///      * Define all HTMLTable_SubHeader depending of previously defined HTMLTable_SuperHeader
///                                       for the gvien group: HTMLTable_Group::addHeader()
///      * Create all HTMLTable_Row for the given group: HTMLTable_Group::createRow()
///          * Create all HTMLTable_Cell for the given row : HTMLTable_Row::addCell()
/// and so on for each group.
/// When done, call HTMLTable_::display() to render the table.
///
/// A column that don't have any content is collapse
///
/// For further explaination, refer to NetworkPort and all its dependencies (NetworkName, IPAddress,
/// IPNetwork, ...) or Computer_Device and each kind of device.
/// @since 0.84
class HTMLTable_ extends HTMLTable_Base {


   private $groups = array();


   function __construct() {
      parent::__construct(true);
   }


   /**
    * We can define a global name for the table : this will print as header that colspan all columns
    *
    * @param $name the name to print inside the header
    *
    * @return nothing
   **/
   function setTitle($name) {
      $this->title = $name;
   }


   /**
    * create a new HTMLTable_SuperHeader
    *
    * @param $header_name (string)           The name that can be refered by getHeader()
    * @param $content (string or array)      The content (see HTMLTable_Entity for the format)
    *                                        of the header
    * @param $father (HTMLTable_SuperHeader) the father of this header (default NULL = none)
    *
    * @return nothing
   **/
   function addHeader($header_name, $content, HTMLTable_SuperHeader $father = NULL) {

      if (count($this->groups) > 0) {
         throw new Exception('Implementation error: must define all headers before any subgroups');
      }
      return $this->appendHeader(new HTMLTable_SuperHeader($this, $header_name, $content,
                                                           $father));
   }


   /**
    * @param $name (string)             The name  of the group, to be able to retrieve the group
    *                                   later with HTMLTable_::getHeader()
    * @param $content (string or array) The title of the group : display before the group itself
    *
    * TODO : study to be sure that the order is the one we have defined ...
    *
    * @return nothing
   **/
   function createGroup($name, $content) {

      if (!empty($name)) {
         if (!isset($this->groups[$name])) {
            $this->groups[$name] = new HTMLTable_Group($this, $name, $content);
         }
      }
      return $this->getGroup($name);
   }


   /**
    * Retrieve a group by its name
    *
    * @param $group_name (string) the group name
    *
    * @return nothing
   **/
   function getGroup($group_name) {

      if (isset($this->groups[$group_name])) {
         return $this->groups[$group_name];
      }
      return false;
   }


   /**
    * Display the super headers, for the global table, or the groups
   **/
   function displaySuperHeader() {

      echo "\t\t<tr>";
      foreach ($this->getHeaderOrder() as $header_name) {
         $header = $this->getHeader($header_name);
         echo "\t\t";
         $header->displayTableHeader(true);
         echo "\n";
      }
      echo "</tr>\n";
   }


   /**
    * get the total number of rows (ie.: the sum of each group number of rows)
    *
    * Beware that a row is counted only if it is not empty (ie.: at least one addCell)
    *
    * @return the total number of rows
   **/
   function getNumberOfRows() {

      $numberOfRow = 0;
      foreach ($this->groups as $group) {
         $numberOfRow += $group->getNumberOfRows();
      }
      return $numberOfRow;
   }


   /**
    * Display the table itself
    *
    * @param $params (array):
    *    'html_id'                      the global HTML ID of the table
    *    'display_thead'                display the header before the first group
    *    'display_tfoot'                display the header at the end of the table
    *    'display_super_for_each_group' display the super header befor each group
    *    'display_title_for_each_group' display the title of each group
    *
    * @return nothing (display only)
   **/
   function display(array $params) {

      $p['html_id']                      = '';
      $p['display_thead']                = true;
      $p['display_tfoot']                = true;

      foreach ($params as $key => $val) {
         $p[$key] = $val;
      }

      foreach ($this->groups as $group) {
         $group->prepareDisplay();
      }

      $totalNumberOfRow = $this->getNumberOfRows();

      $totalNumberOfColumn = 0;
      foreach ($this->getHeaders() as $header) {
         $colspan = $header['']->getColSpan();
         $totalNumberOfColumn += $colspan;
      }

      echo "<table class='tab_cadre_fixe'";
      if (!empty($p['html_id'])) {
         echo " id='".$p['html_id']."'";
      }
      echo ">";

      if (!empty($this->title)) {
         echo "\t\t<tr><th colspan='$totalNumberOfColumn'>".$this->title."</th></tr>\n";
      }

      if ($totalNumberOfRow == 0) {
         echo "\t\t<tr><td class='center' colspan='$totalNumberOfColumn'>" . __('None') .
              "</td></tr>\n";

      } else {

         if ($p['display_thead']) {
            echo "\t<thead>\n";
            $this->displaySuperHeader();
            echo "\t</thead>\n";
         }

         foreach ($this->groups as $group) {
            $group->display($totalNumberOfColumn, $p);
         }

         if ($p['display_tfoot']) {
            echo "\t<tfoot>\n";
            $this->displaySuperHeader();
            echo "\t</tfoot>\n";
         }

     }

      echo "</table>\n";

   }


}
?>
