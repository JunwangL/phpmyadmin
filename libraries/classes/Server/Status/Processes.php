<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * functions for displaying processes list
 *
 * @usedby  server_status_processes.php
 *
 * @package PhpMyAdmin
 */
declare(strict_types=1);

namespace PhpMyAdmin\Server\Status;

use PhpMyAdmin\Message;
use PhpMyAdmin\Server\Status\Data;
use PhpMyAdmin\Util;
use PhpMyAdmin\Url;

/**
 * PhpMyAdmin\Server\Status\Processes class
 *
 * @package PhpMyAdmin
 */
class Processes
{
    /**
     * Prints html for auto refreshing processes list
     *
     * @return string
     */
    public static function getHtmlForProcessListAutoRefresh()
    {
        $notice = Message::notice(
            __(
                'Note: Enabling the auto refresh here might cause '
                . 'heavy traffic between the web server and the MySQL server.'
            )
        )->getDisplay();
        $retval  = $notice . '<div class="tabLinks">';
        $retval .= '<label>' . __('Refresh rate') . ': ';
        $retval .= Data::getHtmlForRefreshList(
            'refreshRate',
            5,
            [2, 3, 4, 5, 10, 20, 40, 60, 120, 300, 600, 1200]
        );
        $retval .= '</label>';
        $retval .= '<a id="toggleRefresh" href="#">';
        $retval .= Util::getImage('play') . __('Start auto refresh');
        $retval .= '</a>';
        $retval .= '</div>';
        return $retval;
    }

    /**
     * Prints Server Process list
     *
     * @return string
     */
    public static function getHtmlForServerProcesslist()
    {
        $url_params = [];

        $show_full_sql = ! empty($_REQUEST['full']);
        if ($show_full_sql) {
            $url_params['full'] = 1;
            $full_text_link = 'server_status_processes.php' . Url::getCommon(
                [],
                '?'
            );
        } else {
            $full_text_link = 'server_status_processes.php' . Url::getCommon(
                ['full' => 1]
            );
        }

        // This array contains display name and real column name of each
        // sortable column in the table
        $sortable_columns = [
            [
                'column_name' => __('ID'),
                'order_by_field' => 'Id'
            ],
            [
                'column_name' => __('User'),
                'order_by_field' => 'User'
            ],
            [
                'column_name' => __('Host'),
                'order_by_field' => 'Host'
            ],
            [
                'column_name' => __('Database'),
                'order_by_field' => 'db'
            ],
            [
                'column_name' => __('Command'),
                'order_by_field' => 'Command'
            ],
            [
                'column_name' => __('Time'),
                'order_by_field' => 'Time'
            ],
            [
                'column_name' => __('Status'),
                'order_by_field' => 'State'
            ],
            [
                'column_name' => __('Progress'),
                'order_by_field' => 'Progress'
            ],
            [
                'column_name' => __('SQL query'),
                'order_by_field' => 'Info'
            ]
        ];
        $sortableColCount = count($sortable_columns);

        $sql_query = $show_full_sql
            ? 'SHOW FULL PROCESSLIST'
            : 'SHOW PROCESSLIST';
        if ((! empty($_REQUEST['order_by_field'])
            && ! empty($_REQUEST['sort_order']))
            || (! empty($_REQUEST['showExecuting']))
        ) {
            $sql_query = 'SELECT * FROM `INFORMATION_SCHEMA`.`PROCESSLIST` ';
        }
        if (! empty($_REQUEST['showExecuting'])) {
            $sql_query .= ' WHERE state != "" ';
        }
        if (!empty($_REQUEST['order_by_field']) && !empty($_REQUEST['sort_order'])) {
            $sql_query .= ' ORDER BY '
                . Util::backquote($_REQUEST['order_by_field'])
                . ' ' . $_REQUEST['sort_order'];
        }

        $result = $GLOBALS['dbi']->query($sql_query);

        $retval = '<div class="responsivetable">';
        $retval .= '<table id="tableprocesslist" '
            . 'class="data clearfloat noclick sortable">';
        $retval .= '<thead>';
        $retval .= '<tr>';
        $retval .= '<th>' . __('Processes') . '</th>';
        foreach ($sortable_columns as $column) {
            $is_sorted = ! empty($_REQUEST['order_by_field'])
                && ! empty($_REQUEST['sort_order'])
                && ($_REQUEST['order_by_field'] == $column['order_by_field']);

            $column['sort_order'] = 'ASC';
            if ($is_sorted && $_REQUEST['sort_order'] === 'ASC') {
                $column['sort_order'] = 'DESC';
            }
            if (isset($_REQUEST['showExecuting'])) {
                $column['showExecuting'] = 'on';
            }

            $retval .= '<th>';
            $columnUrl = Url::getCommon($column);
            $retval .= '<a href="server_status_processes.php' . $columnUrl . '" class="sortlink">';

            $retval .= $column['column_name'];

            if ($is_sorted) {
                $asc_display_style = 'inline';
                $desc_display_style = 'none';
                if ($_REQUEST['sort_order'] === 'DESC') {
                    $desc_display_style = 'inline';
                    $asc_display_style = 'none';
                }
                $retval .= '<img class="icon ic_s_desc soimg" alt="'
                    . __('Descending') . '" title="" src="themes/dot.gif" '
                    . 'style="display: ' . $desc_display_style . '" />';
                $retval .= '<img class="icon ic_s_asc soimg hide" alt="'
                    . __('Ascending') . '" title="" src="themes/dot.gif" '
                    . 'style="display: ' . $asc_display_style . '" />';
            }

            $retval .= '</a>';

            if (0 === --$sortableColCount) {
                $retval .= '<a href="' . $full_text_link . '">';
                if ($show_full_sql) {
                    $retval .= Util::getImage('s_partialtext', __('Truncate Shown Queries'));
                } else {
                    $retval .= Util::getImage('s_fulltext', __('Show Full Queries'));
                }
                $retval .= '</a>';
            }
            $retval .= '</th>';
        }

        $retval .= '</tr>';
        $retval .= '</thead>';
        $retval .= '<tbody>';

        while ($process = $GLOBALS['dbi']->fetchAssoc($result)) {
            $retval .= self::getHtmlForServerProcessItem(
                $process,
                $show_full_sql
            );
        }
        $retval .= '</tbody>';
        $retval .= '</table>';
        $retval .= '</div>';

        return $retval;
    }

    /**
     * Returns the html for the list filter
     *
     * @return string
     */
    public static function getHtmlForProcessListFilter()
    {
        $showExecuting = '';
        if (! empty($_REQUEST['showExecuting'])) {
            $showExecuting = ' checked="checked"';
        }

        $url_params = [
            'ajax_request' => true,
            'full' => (isset($_REQUEST['full']) ? $_REQUEST['full'] : ''),
            'column_name' => (isset($_REQUEST['column_name']) ? $_REQUEST['column_name'] : ''),
            'order_by_field'
                => (isset($_REQUEST['order_by_field']) ? $_REQUEST['order_by_field'] : ''),
            'sort_order' => (isset($_REQUEST['sort_order']) ? $_REQUEST['sort_order'] : ''),
        ];

        $retval  = '';
        $retval .= '<fieldset id="tableFilter">';
        $retval .= '<legend>' . __('Filters') . '</legend>';
        $retval .= '<form action="server_status_processes.php">';
        $retval .= Url::getHiddenInputs($url_params);
        $retval .= '<input type="submit" value="' . __('Refresh') . '" />';
        $retval .= '<div class="formelement">';
        $retval .= '<input' . $showExecuting . ' type="checkbox" name="showExecuting"'
            . ' id="showExecuting" class="autosubmit"/>';
        $retval .= '<label for="showExecuting">';
        $retval .= __('Show only active');
        $retval .= '</label>';
        $retval .= '</div>';
        $retval .= '</form>';
        $retval .= '</fieldset>';

        return $retval;
    }

    /**
     * Prints Every Item of Server Process
     *
     * @param array $process       data of Every Item of Server Process
     * @param bool  $show_full_sql show full sql or not
     *
     * @return string
     */
    public static function getHtmlForServerProcessItem(array $process, $show_full_sql)
    {
        // Array keys need to modify due to the way it has used
        // to display column values
        if ((! empty($_REQUEST['order_by_field']) && ! empty($_REQUEST['sort_order']))
            || (! empty($_REQUEST['showExecuting']))
        ) {
            foreach (array_keys($process) as $key) {
                $new_key = ucfirst(mb_strtolower($key));
                if ($new_key !== $key) {
                    $process[$new_key] = $process[$key];
                    unset($process[$key]);
                }
            }
        }

        $url_params = [
            'kill' => $process['Id'],
            'ajax_request' => true
        ];
        $kill_process = 'server_status_processes.php' . Url::getCommon($url_params);

        $retval  = '<tr>';
        $retval .= '<td><a class="ajax kill_process" href="' . $kill_process . '">'
            . __('Kill') . '</a></td>';
        $retval .= '<td class="value">' . $process['Id'] . '</td>';
        $retval .= '<td>' . htmlspecialchars($process['User']) . '</td>';
        $retval .= '<td>' . htmlspecialchars($process['Host']) . '</td>';
        $retval .= '<td>' . ((! isset($process['db'])
                || strlen($process['db']) === 0)
                ? '<i>' . __('None') . '</i>'
                : htmlspecialchars($process['db'])) . '</td>';
        $retval .= '<td>' . htmlspecialchars($process['Command']) . '</td>';
        $retval .= '<td class="value">' . $process['Time'] . '</td>';
        $processStatusStr = empty($process['State']) ? '---' : $process['State'];
        $retval .= '<td>' . $processStatusStr . '</td>';
        $processProgress = empty($process['Progress']) ? '---' : $process['Progress'];
        $retval .= '<td>' . $processProgress . '</td>';
        $retval .= '<td>';

        if (empty($process['Info'])) {
            $retval .= '---';
        } else {
            $retval .= Util::formatSql($process['Info'], ! $show_full_sql);
        }
        $retval .= '</td>';
        $retval .= '</tr>';

        return $retval;
    }
}
