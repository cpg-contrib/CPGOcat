<?php

/*************************
  Coppermine Photo Gallery
  ************************
  Copyright (c) 2003-2005 Coppermine Dev Team
  v1.1 originaly written by Gregory DEMAR

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.
  ********************************************
  Coppermine version: 1.4.1
  $Source: /cvsroot/cpg-contrib/CPGOcat/codebase.php,v $
  $Revision: 1.3 $
  $Author: donnoman $
  $Date: 2007/04/29 13:51:52 $
**********************************************/

if (!defined('IN_COPPERMINE'))
    die('Not in Coppermine...');

define('OCAT_POP_TO_CATLIST',false); //true: enables popping to the anchor at the cat list on on clicks of the column headings
define('OCAT_SHOW_DIR',true); //true: shows directional triangle of sort in column heading

$thisplugin->add_filter('plugin_block', 'ocat_block');
$thisplugin->add_action('page_start', 'ocat_start');

global $ocat_template_cat_list;
// HTML template for the category list
$ocat_template_cat_list = <<<EOT
<!-- BEGIN header -->
        <tr>
                <td class="tableh1" width="80%"><a name="cat_list"></a>{CATEGORY}</td>
                <td class="tableh1" width="10%" align="center">{LAST_UP}</td>
                <td class="tableh1" width="10%" align="center">{ALBUMS}</td>
                <td class="tableh1" width="10%" align="center">{PICTURES}</td>
        </tr>
<!-- END header -->
<!-- BEGIN catrow_noalb -->
        <tr>
                <td class="tableh2" colspan="4"><table border="0" ><tr><td>{CAT_THUMB}</td><td><span class="catlink"><b>{CAT_TITLE}</b></span>{CAT_DESC}</td></tr></table></td>
        </tr>
<!-- END catrow_noalb -->
<!-- BEGIN catrow -->
        <tr>
                <td class="tableb"><table border="0"><tr><td>{CAT_THUMB}</td><td><span class="catlink"><b>{CAT_TITLE}</b></span>{CAT_DESC}</td></tr></table></td>
                <td class="tableb" align="center" style="white-space:nowrap">{LAST_UP}</td>
                <td class="tableb" align="center">{ALB_COUNT}</td>
                <td class="tableb" align="center">{PIC_COUNT}</td>
        <tr>
            <td class="tableb" colspan="4">{CAT_ALBUMS}</td>
        </tr>
<!-- END catrow -->
<!-- BEGIN footer -->
        <tr>
                <td colspan="4" class="tableh1" align="center"><span class="statlink"><b>{STATISTICS}</b></span></td>
        </tr>
<!-- END footer -->
<!-- BEGIN spacer -->
        <img src="images/spacer.gif" width="1" height="7" border="0" alt="" /><br />
<!-- END spacer -->

EOT;

function ocat_start() {
    /**
     * overridable language variables, set them in the language file
     * ie:
     * $lang_cat_list = array(
     *    'category' => 'Category',
     *    'albums' => 'Albums',
     *    'pictures' => 'Files',
     *    'last_up' => 'Last Up',
     *  );
     */
    $ocat_lang['lang_cat_list']['last_up']='Last Up';
    foreach ($ocat_lang as $lang_var => $values) {
        foreach ($values as $key => $value) {
            if (!isset($GLOBALS[$lang_var][$key])) {
                $GLOBALS[$lang_var][$key]=$value;
            }
        }
    }
}


function ocat_block($matches) {
    global $cat, $cat_data, $breadcrumb, $cat_data, $statistics;

    if (is_array($matches)) {
        switch ($matches[1]) {
            case 'catlist' :
                ocat_get_cat_list($breadcrumb, $cat_data, $statistics);
                if ($breadcrumb != '' || count($cat_data) > 0)
                    ocat_display_cat_list($breadcrumb, $cat_data, $statistics);
                if (isset ($cat) && $cat == USER_GAL_CAT) {
                    list_users();
                }
                flush();
                $matches[1] = '';
                break;
        }
    }
    return $matches;
}

/**
 * ocat_get_subcat_data()
 *
 * Get the data about the sub categories which are going to be shown on the index page, this function is called recursively
 *
 * @param integer $parent Parent Category
 * @param array $cat_data
 * @param array $album_set_array
 * @param integer $level Level being displayed
 * @param string $ident String to use as indentation for Categories
 * @return void
 **/
function ocat_get_subcat_data($parent, &$cat_data, &$album_set_array, $level, $ident = '', $lineage = '') {
    global $CONFIG, $HIDE_USER_CAT, $FORBIDDEN_SET, $cpg_show_private_album;

    $album_filter = '';
    $pic_filter = '';
    if (!empty ($FORBIDDEN_SET) && !$cpg_show_private_album) {
        $album_filter = ' and '.str_replace('p.', 'a.', $FORBIDDEN_SET);
        $pic_filter = ' and '.str_replace('p.', $CONFIG['TABLE_PICTURES'].'.', $FORBIDDEN_SET);
    }

    $result = cpg_db_query("SELECT cid, name, description, thumb FROM {$CONFIG['TABLE_CATEGORIES']} WHERE parent = '$parent'  ORDER BY pos");

    if (mysql_num_rows($result) > 0) {
        $rowset = cpg_db_fetch_rowset($result);
        foreach ($rowset as $subcat) {
            if ($subcat['cid'] == USER_GAL_CAT) {
                $sql = "SELECT aid FROM {$CONFIG['TABLE_ALBUMS']} as a WHERE category>=".FIRST_USER_CAT.$album_filter;
                $result = cpg_db_query($sql);
                $album_count = mysql_num_rows($result);
                while ($row = mysql_fetch_array($result)) {
                    $album_set_array[] = $row['aid'];
                } // while
                mysql_free_result($result);

                $result = cpg_db_query("SELECT count(*) FROM {$CONFIG['TABLE_PICTURES']} as p, {$CONFIG['TABLE_ALBUMS']} as a WHERE p.aid = a.aid AND category >= ".FIRST_USER_CAT.$album_filter);
                $nbEnr = mysql_fetch_array($result);
                $pic_count = $nbEnr[0];

                $subcat['description'] = preg_replace("/<br.*?>[\r\n]*/i", '<br />'.$ident, bb_decode($subcat['description']));

                $link = $ident."<a href=\"index.php?cat={$subcat['cid']}\">{$subcat['name']}</a>";

                if ($album_count) {
                    // begin custom cat statistics
                    $result = cpg_db_query("SELECT ctime FROM {$CONFIG['TABLE_PICTURES']} as p, {$CONFIG['TABLE_ALBUMS']} as a WHERE p.aid = a.aid AND category >= ".FIRST_USER_CAT.$album_filter." ORDER BY p.ctime DESC LIMIT 1");
                    $row = mysql_fetch_array($result);
                    mysql_free_result($result);
                    $subcat['last_up'] = $row['ctime'];
                    // end custom cat statistics
                    $cat_data[] = array ($link, $subcat['description'], $album_count, $pic_count, 'cat_albums' => $cat_albums, 'cat_thumb' => $user_thumb, 'last_up' => $subcat['last_up'], 'category' => $subcat['name'], 'level' => $level);
                    $last_cat = count($cat_data) - 1;
                    $HIDE_USER_CAT = 0;
                } else {
                    $HIDE_USER_CAT = 1;
                    unset($last_cat);
                }
            } else {
                $unaliased_album_filter = str_replace('a.', '', $album_filter);
                $result = cpg_db_query("SELECT aid FROM {$CONFIG['TABLE_ALBUMS']} WHERE category = {$subcat['cid']}".$unaliased_album_filter);
                $album_count = mysql_num_rows($result);
                while ($row = mysql_fetch_array($result)) {
                    $album_set_array[] = $row['aid'];
                } // while
                mysql_free_result($result);

                $result = cpg_db_query("SELECT count(*) FROM {$CONFIG['TABLE_PICTURES']} as p, {$CONFIG['TABLE_ALBUMS']} as a WHERE p.aid = a.aid AND category = {$subcat['cid']}".$album_filter);
                $nbEnr = mysql_fetch_array($result);
                mysql_free_result($result);
                $pic_count = $nbEnr[0];
                if ($subcat['thumb'] > 0) {
                    $sql = "SELECT filepath, filename, url_prefix, pwidth, pheight "."FROM {$CONFIG['TABLE_PICTURES']} "."WHERE pid='{$subcat['thumb']}'".$pic_filter;
                    $result = cpg_db_query($sql);
                    if (mysql_num_rows($result)) {
                        $picture = mysql_fetch_array($result);
                        mysql_free_result($result);
                        $pic_url = get_pic_url($picture, 'thumb');
                        if (!is_image($picture['filename'])) {
                            $image_info = getimagesize(urldecode($pic_url));
                            $picture['pwidth'] = $image_info[0];
                            $picture['pheight'] = $image_info[1];
                        }
                        $image_size = compute_img_size($picture['pwidth'], $picture['pheight'], $CONFIG['alb_list_thumb_size']);
                        $user_thumb = "<img src=\"".$pic_url."\" class=\"image\" {$image_size['geom']} border=\"0\" alt=\"\" />";
                        $user_thumb = "<a href=\"index.php?cat={$subcat['cid']}\">".$user_thumb."</a>";
                    }
                } else {
                    $user_thumb = "";
                }
                $subcat['name'] = $subcat['name'];
                $subcat['description'] = preg_replace("/<br.*?>[\r\n]*/i", '<br />', bb_decode($subcat['description']));
                $link = "<a href=\"index.php?cat={$subcat['cid']}\">{$subcat['name']}</a>";
                $user_thumb = $ident.$user_thumb;

                // begin custom cat statistics
                $result = cpg_db_query("SELECT ctime FROM {$CONFIG['TABLE_PICTURES']} as p, {$CONFIG['TABLE_ALBUMS']} as a WHERE p.aid = a.aid AND category = {$subcat['cid']} {$album_filter} ORDER BY p.ctime DESC LIMIT 1");
                $row = mysql_fetch_array($result);
                mysql_free_result($result);
                $subcat['last_up'] = $row['ctime'];
                // end custom cat statistics

                if ($pic_count == 0 && $album_count == 0) {
                    $user_thumb = $ident;
                    //echo "\nPic count or album count 0";
                    $cat_data[] = array ($link, $subcat['description'], 'cat_thumb' => $user_thumb, 'last_up' => $subcat['last_up'], 'category' => $subcat['name'], 'level' => $level);
                    $last_cat = count($cat_data) - 1;
                } else {
                    // Check if you need to show subcat_level
                    if ($level == $CONFIG['subcat_level']) {
                        $cat_albums = list_cat_albums($subcat['cid']);
                    } else {
                        $cat_albums = '';
                    }
                    $cat_data[] = array ($link, $subcat['description'], $album_count, $pic_count, 'cat_albums' => $cat_albums, 'cat_thumb' => $user_thumb, 'last_up' => $subcat['last_up'], 'category' => $subcat['name'], 'level' => $level);
                    $last_cat = count($cat_data) - 1;
                }
            }

            if ($level > 1) {
                $children = ocat_get_subcat_data($subcat['cid'], $cat_data, $album_set_array, $level -1, $ident."</td><td><img src=\"images/spacer.gif\" width=\"20\" height=\"1\"></td><td>");
            }
            if (isset ($last_cat)) {
                $CATEGORIES[] = array_merge($cat_data[$last_cat], array ('children' => isset ($children) ? $children : ''));
            }
        }

    }

    return isset ($CATEGORIES) ? $CATEGORIES : '';
}

//category sort
function cat_multisort($cat_data) {
    global $idx;
    //build the arrays for multisort
    foreach ($cat_data as $category) {
        $cat_sort['albums'][] = isset ($category[2]) ? $category[2] : '';
        $cat_sort['pictures'][] = isset ($category[3]) ? $category[3] : '';
        $cat_sort['category'][] = $category['category'];
        $cat_sort['last_up'][] = $category['last_up'];
        $cat_sort['level'][] = $category['level'];
    }
    $cat_data_sorted = $cat_data; //if you don't do this multisort sorts the global outside this scope

    array_multisort($cat_sort[$idx[0][0]], $idx[0][1], $cat_sort[$idx[1][0]], $idx[1][1], $cat_sort[$idx[2][0]], $idx[2][1], $cat_sort[$idx[3][0]], $idx[3][1], $cat_data_sorted);

    foreach ($cat_data_sorted as $key => $category) {
        if (is_array($category['children'])) {
            $cat_data_sorted[$key]['children'] = cat_multisort($category['children']);
        }
    }

    return $cat_data_sorted;
}

function cat_flatten($CATEGORIES, & $cat_data) {
    foreach ($CATEGORIES as $category) {
        $cat_data[] = $category;
        if (is_array($category['children'])) {
            cat_flatten($category['children'], $cat_data);
        }
    }
}

//Custom Category Sorting
function cat_sort(& $CATEGORIES, & $cat_data) {
    global $idx, $CONFIG;

    //establish default sort order - also used in theme.php
    $idx[0] = array ('last_up', SORT_DESC);
    $idx[1] = array ('category', SORT_ASC);
    $idx[2] = array ('albums', SORT_DESC);
    $idx[3] = array ('pictures', SORT_DESC);

    if (isset ($_REQUEST['catsort'])) {
        //make changes to the sort order
        $catsort = explode(';', $_REQUEST['catsort']);
        //if its anything but 'desc' make it ASC
        $catsort[1] = (isset ($catsort[1]) && stristr($catsort[1], 'desc')) ? SORT_DESC : SORT_ASC;
    } else {
        $catsort = $idx[0];
    }

    switch ($catsort[0]) {
        case 'last_up' :
            unset ($idx[0]);
            array_unshift($idx, array ('last_up', $catsort[1]));
            break;
        case 'category' :
            unset ($idx[1]);
            array_unshift($idx, array ('category', $catsort[1]));
            break;
        case 'albums' :
            unset ($idx[2]);
            array_unshift($idx, array ('albums', $catsort[1]));
            break;
        case 'pictures' :
            unset ($idx[3]);
            array_unshift($idx, array ('pictures', $catsort[1]));
            break;
    }

    $CATEGORIES = cat_multisort($CATEGORIES);
    $cat_data = array ();
    cat_flatten($CATEGORIES, $cat_data);

}

// List all categories
function ocat_get_cat_list(& $breadcrumb, & $cat_data, & $statistics) {
    global $CONFIG, $ALBUM_SET, $CURRENT_CAT_NAME, $BREADCRUMB_TEXT, $STATS_IN_ALB_LIST, $FORBIDDEN_SET;
    global $HIDE_USER_CAT, $cpg_show_private_album;
    global $cat;
    global $lang_list_categories, $lang_errors;
    global $idx;

    // Build the breadcrumb
    breadcrumb($cat, $breadcrumb, $BREADCRUMB_TEXT);
    // Build the category list
    $cat_data = array ();
    $album_set_array = array ();
    $CATEGORIES = ocat_get_subcat_data($cat, $cat_data, $album_set_array, $CONFIG['subcat_level']);

    if (is_array($CATEGORIES))
        cat_sort($CATEGORIES, $cat_data);

    $album_filter = '';
    $pic_filter = '';
    $cat = (int) $cat;
    if (!empty ($FORBIDDEN_SET) && !$cpg_show_private_album) {
        $album_filter = ' and '.str_replace('p.', 'a.', $FORBIDDEN_SET);
        $pic_filter = ' and '.$FORBIDDEN_SET;
    }
    // Add the albums in the current category to the album set
    // if ($cat) {
    if ($cat == USER_GAL_CAT) {
        $sql = "SELECT aid FROM {$CONFIG['TABLE_ALBUMS']} as a WHERE category >= ".FIRST_USER_CAT.$album_filter;
        $result = cpg_db_query($sql);
    } else {
        $sql = "SELECT aid FROM {$CONFIG['TABLE_ALBUMS']} as a WHERE category = '$cat'".$album_filter;
        $result = cpg_db_query($sql);
    }
    while ($row = mysql_fetch_array($result)) {
        $album_set_array[] = $row['aid'];
    } // while
    mysql_free_result($result);
    // }
    if (count($album_set_array) && $cat) {
        $set = '';
        foreach ($album_set_array as $album)
            $set .= $album.',';
        $set = substr($set, 0, -1);
        $current_album_set = "AND aid IN ($set) ";
        $ALBUM_SET .= $current_album_set;
    }
    elseif ($cat) {
        $current_album_set = "AND aid IN (-1) ";
        $ALBUM_SET .= $current_album_set;
    }
    // Gather gallery statistics
    if ($cat == 0) {
        $result = cpg_db_query("SELECT count(*) FROM {$CONFIG['TABLE_ALBUMS']} as a WHERE 1".$album_filter);
        $nbEnr = mysql_fetch_array($result);
        $album_count = $nbEnr[0];
        mysql_free_result($result);

        $sql = "SELECT count(*) FROM {$CONFIG['TABLE_PICTURES']} as p ".'LEFT JOIN '.$CONFIG['TABLE_ALBUMS'].' as a '.'ON a.aid=p.aid '.'WHERE 1'.$pic_filter;
        $result = cpg_db_query($sql);
        $nbEnr = mysql_fetch_array($result);
        $picture_count = $nbEnr[0];
        mysql_free_result($result);

        $sql = "SELECT count(*) FROM {$CONFIG['TABLE_COMMENTS']} as c ".'LEFT JOIN '.$CONFIG['TABLE_PICTURES'].' as p '.'ON c.pid=p.pid '.'LEFT JOIN '.$CONFIG['TABLE_ALBUMS'].' as a '.'ON a.aid=p.aid '.'WHERE 1'.$pic_filter;
        $result = cpg_db_query($sql);
        $nbEnr = mysql_fetch_array($result);
        $comment_count = $nbEnr[0];
        mysql_free_result($result);

        $sql = "SELECT count(*) FROM {$CONFIG['TABLE_CATEGORIES']} WHERE 1";
        $result = cpg_db_query($sql);
        $nbEnr = mysql_fetch_array($result);
        $cat_count = $nbEnr[0] - $HIDE_USER_CAT;
        mysql_free_result($result);

        $sql = "SELECT sum(hits) FROM {$CONFIG['TABLE_PICTURES']} as p ".'LEFT JOIN '.$CONFIG['TABLE_ALBUMS'].' as a '.'ON p.aid=a.aid '.'WHERE 1'.$pic_filter;
        $result = cpg_db_query($sql);
        $nbEnr = mysql_fetch_array($result);
        $hit_count = (int) $nbEnr[0];
        mysql_free_result($result);

        if (count($cat_data)) {
            $statistics = strtr($lang_list_categories['stat1'], array ('[pictures]' => $picture_count, '[albums]' => $album_count, '[cat]' => $cat_count, '[comments]' => $comment_count, '[views]' => $hit_count));
        } else {
            $STATS_IN_ALB_LIST = true;
            $statistics = strtr($lang_list_categories['stat3'], array ('[pictures]' => $picture_count, '[albums]' => $album_count, '[comments]' => $comment_count, '[views]' => $hit_count));
        }
    }
    elseif ($cat >= FIRST_USER_CAT && $ALBUM_SET) {
        $result = cpg_db_query("SELECT count(*) FROM {$CONFIG['TABLE_ALBUMS']} WHERE 1 $current_album_set");
        $nbEnr = mysql_fetch_array($result);
        $album_count = $nbEnr[0];
        mysql_free_result($result);

        $result = cpg_db_query("SELECT count(*) FROM {$CONFIG['TABLE_PICTURES']} WHERE 1 $current_album_set");
        $nbEnr = mysql_fetch_array($result);
        $picture_count = $nbEnr[0];
        mysql_free_result($result);

        $result = cpg_db_query("SELECT sum(hits) FROM {$CONFIG['TABLE_PICTURES']} WHERE 1 $current_album_set");
        $nbEnr = mysql_fetch_array($result);
        $hit_count = (int) $nbEnr[0];
        mysql_free_result($result);

        $statistics = strtr($lang_list_categories['stat2'], array ('[pictures]' => $picture_count, '[albums]' => $album_count, '[views]' => $hit_count));
    } else {
        $statistics = '';
    }
}

function ocat_display_cat_list($breadcrumb, & $cat_data, $statistics) {
    global $ocat_template_cat_list, $lang_cat_list, $lastup_date_fmt;
    global $idx, $cat;

    if (count($cat_data) > 0) {
        starttable('100%');
        $params = array ('{CATEGORY}' => $lang_cat_list['category'], '{ALBUMS}' => $lang_cat_list['albums'], '{PICTURES}' => $lang_cat_list['pictures'], '{LAST_UP}' => $lang_cat_list['last_up'],);

        //whatever is the current primary sort reverse it
        $idx[0][1] = ($idx[0][1] == SORT_ASC) ? SORT_DESC : SORT_ASC;

        if (OCAT_POP_TO_CATLIST) {
            $cat_list_anchor='#cat_list';
        } else {
            $cat_list_anchor='';
        }

        //wrap the titles with hrefs to the assigned sort
        foreach ($idx as $key => $index) {
            if (!$key && OCAT_SHOW_DIR) {
            $decending='&nbsp;<img src="images/descending.gif" alt="" border="0" />';
            $ascending='&nbsp;<img src="images/ascending.gif" alt="" border="0" />';
            } else {
                $ascending=$decending='';
            }
            $params['{'.strtoupper($index[0]).'}'] = '<span class="statlink"><a href="index.php?cat='.$cat.'&amp;catsort='.$index[0]. (($index[1] == SORT_ASC) ? '' : ';desc').$cat_list_anchor.'" style="font-weight:'. (($key) ? 'normal' : 'bold').'">'.$params['{'.strtoupper($index[0]).'}']. (($index[1] == SORT_ASC) ? $decending : $ascending).'</a></span>';
        }

        $template = template_extract_block($ocat_template_cat_list, 'header');
        echo template_eval($template, $params);
    }

    $template_noabl = template_extract_block($ocat_template_cat_list, 'catrow_noalb');
    $template = template_extract_block($ocat_template_cat_list, 'catrow');
    foreach ($cat_data as $category) {
        if (count($category) == 3) {
            $params = array ('{CAT_TITLE}' => $category[0], '{CAT_THUMB}' => $category['cat_thumb'], '{CAT_DESC}' => $category[1]);
            echo template_eval($template_noabl, $params);
        }
        elseif (isset ($category['cat_albums']) && ($category['cat_albums'] != '')) {
            $params = array ('{CAT_TITLE}' => $category[0], '{CAT_THUMB}' => $category['cat_thumb'], '{CAT_DESC}' => $category[1], '{CAT_ALBUMS}' => $category['cat_albums'], '{ALB_COUNT}' => $category[2], '{PIC_COUNT}' => $category[3], '{LAST_UP}' => isset ($category['last_up']) ? localised_date($category['last_up'], $lastup_date_fmt) : '',);
            echo template_eval($template, $params);
        } else {
            $params = array ('{CAT_TITLE}' => $category[0], '{CAT_THUMB}' => $category['cat_thumb'], '{CAT_DESC}' => $category[1], '{CAT_ALBUMS}' => '', '{ALB_COUNT}' => isset ($category[2]) ? $category[2] : '', '{PIC_COUNT}' => isset ($category[3]) ? $category[3] : '', '{LAST_UP}' => isset ($category['last_up']) ? localised_date($category['last_up'], $lastup_date_fmt) : '',);
            echo template_eval($template, $params);
        }
    }

    if ($statistics && count($cat_data) > 0) {
        $template = template_extract_block($ocat_template_cat_list, 'footer');
        $params = array ('{STATISTICS}' => $statistics);
        echo template_eval($template, $params);
    }

    if (count($cat_data) > 0)
        endtable();
    echo template_extract_block($ocat_template_cat_list, 'spacer');
}

?>