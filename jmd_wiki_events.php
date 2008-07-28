<?php

$plugin['version'] = '0.1';
$plugin['author'] = 'Jon-Michael Deldin';
$plugin['author_uri'] = 'http://jmdeldin.com/';
$plugin['description'] = 'Returns Wikipedia events based on the current or article date.';
$plugin['type'] = 1;

if (!defined('txpinterface')) @include_once '../zem_tpl.php';

if (0) {
?>
# --- BEGIN PLUGIN HELP ---

@<txp:jmd_wiki_events/>@ is a rewrite of "mdn_wikitoday":http://forum.textpattern.com/viewtopic.php?id=4993. It returns a list of Wikipedia events based on either the article's date or the current date, depending on its context. Wikipedia events are stored in a table for two months before being updated. 

Before you use the plugin, ensure your server uses PHP5 and that you've installed the table via Extensions>jmd_wiki_events.

h2. Tag overview

|_. Tag |_. Attributes |_. Context |_. Description |
| @<txp:jmd_wiki_events/>@ | limit, reverse | Page, article | Returns an unordered list of events from Wikipedia. |
| @<txp:jmd_wiki_events_display/>@ | - | @jmd_wiki_events@ | Same as @<txp:jmd_wiki_events/>@. |
| @<txp:jmd_wiki_events_link/>@ | class, title | @jmd_wiki_events@ | Returns a link to the Wikipedia page |
| @<txp:jmd_wiki_events_date/>@ | format | @jmd_wiki_events@ | Returns the date used for the Wikipedia events |

h2(#jmd_wiki_events). <txp:jmd_wiki_events/>

If the tag is used in an article form, the article's date will be used for the events. In a page template, the current date will be used.

|_. Attribute |_. Available values |_. Default value |_. Description |
| @limit@ | INT(Integer) | Unlimited | Limit the number of events (list items) |
| @reverse@ | 1, 0 | 0 | Reverse the sort order of the events |

bc. <txp:jmd_wiki_events/>

h2(#jmd_wiki_events_display). <txp:jmd_wiki_events_display/>

bc. <txp:jmd_wiki_events limit="20">
    This happened today:
    <txp:jmd_wiki_events_display/>
</txp:jmd_wiki_events>

h2(#jmd_wiki_events_link). <txp:jmd_wiki_events_link/>

|_. Attribute |_. Default value |_. Description |
| @class@ | - | @class@ attribute for the link |
| @title@ | - | @title@ attribute for the link |

bc. <txp:jmd_wiki_events>
    <txp:jmd_wiki_events_link>Today's events</txp:jmd_wiki_events_link>
    <txp:jmd_wiki_events_display/>
</txp:jmd_wiki_events>

h2(#jmd_wiki_events_date). <txp:jmd_wiki_events_date/>

|_. Attribute |_. Available values |_. Default value |_. Description |
| @format@ | "strftime format":http://php.net/strftime | @Y-m-d@ | Display the events' date |

bc. <txp:jmd_wiki_events>
    Events for <txp:jmd_wiki_events_date/>
    <txp:jmd_wiki_events_display/>
</txp:jmd_wiki_events>

h2. Example

bc.. <txp:jmd_wiki_events limit="10" reverse="1">
    <p>
        I made <a href="<txp:jmd_wiki_events_link/>">this link</a> all 
        by myself.
    </p>
    
    <h2>
        <txp:jmd_wiki_events_link class="wiki" title="Check out the original">
            <txp:jmd_wiki_events_date/>: Historical events
        </txp:jmd_wiki_events_link>
    </h2>
    
    <txp:jmd_wiki_events_display/>
</txp:jmd_wiki_events>

            
h2. Credits

* Mark Norton for mdn_wikitoday's wicked HTML parser
* "Ruhh" for prompting and testing this plugin

# --- END PLUGIN HELP ---

<?php
}

# --- BEGIN PLUGIN CODE ---

//--------------------------
// admin

if (@txpinterface == 'admin')
{
    add_privs('jmd_wiki_events_prefs', '1');
    register_tab('extensions', 'jmd_wiki_events_prefs', 'jmd_wiki_events');
    register_callback('jmd_wiki_events_prefs', 'jmd_wiki_events_prefs');
}

function jmd_wiki_events_prefs($event, $step)
{
    ob_start('jmd_wiki_events_prefs_head');
    
    // event alias
    $eName = 'jmd_wiki_events_prefs';
    pagetop($eName);
    echo '<div id="jmd_wiki_events_prefs">';

    if (!$step)
    {
        echo fieldset(
            form(
                (
                    fInput('submit', 'install', 'Install', 'publish').
                    eInput($eName).
                    sInput('install')
                )
            ).
            form(
                (
                    fInput('submit', 'uninstall', 'Uninstall', 'publish').
                    eInput($eName).
                    sInput('uninstall')
                ), '', "verify('Are you sure you want to delete all stored events?');"
            ), 'Setup', 'setup'
        );

    }
    elseif ($step == 'install')
    {
        $sql = "CREATE TABLE ".safe_pfx('jmd_wiki_events')."(
            title VARCHAR(15) KEY,
            last_mod DATE,
            contents LONGTEXT
        )";
        $create = safe_query($sql);

        if ($create)
        {
            echo tag('Table created successfully. '.eLink($eName, '', '', '', 'Back to preferences?'), 'p', ' class="ok"');
        }
        else
        {
            echo tag('Database exists. '.eLink($eName, '', '', '', 'Back to preferences?'), 'p', ' class="not-ok"');
        }

    }
    elseif ($step == 'uninstall')
    {
        safe_query("DROP TABLE IF EXISTS ".safe_pfx('jmd_wiki_events'));
        echo tag('Table dropped. '.eLink($eName, '', '', '', 'Back to preferences?'), 'p', ' class="ok"');
    }
    else
    {
        echo tag('Error.', 'h1');
    }
    
    echo '</div>';

}

function jmd_wiki_events_prefs_head($buffer)
{
    $find = '</head>';
    $replace = '
        <style type="text/css">
            #jmd_wiki_events_prefs {
                width: 500px;
                margin: 20px auto;
            }
            fieldset label {
                display: block;
            }
            #setup form {
                display: inline;
            }
            p.not-ok {
                margin-top: 10px;
            }
        </style>
    ';

    return str_replace($find, $replace.$find, $buffer);
}


//-----------------------------
// public

// initialize the wiki events
function jmd_wiki_events($atts, $thing = NULL)
{
    extract(lAtts(array(
        'limit' => NULL,
        'reverse' => 0,
    ), $atts));

    global $jmd_wiki_events;
    $jmd_wiki_events = new JMD_WikiEvents($limit, $reverse);
    
    if ($thing === NULL)
    {
        return jmd_wiki_events_display();
    }
    else
    {
        return parse($thing);
    }
}

// returns a list of events
function jmd_wiki_events_display()
{
    return $GLOBALS['jmd_wiki_events']->display();
}

// links to wikipedia
function jmd_wiki_events_link($atts, $thing = NULL)
{
    extract(lAtts(array(
        'class' => '',
        'title' => '',
    ), $atts));
    
    global $jmd_wiki_events;
    
    if ($thing === NULL)
    {
        return $jmd_wiki_events->uri;
    }
    else
    {
        return tag(parse($thing), 'a', ' rel="external" href="'.$jmd_wiki_events->uri.'"'.
            ($title ? ' title="'.$title.'"' : '').
            ($class ? ' class="'.$class.'"' : '')
        );
    }
}

// returns the current date or the article's date
function jmd_wiki_events_date($atts)
{
    extract(lAtts(array(
        'format' => '',
    ), $atts));
    
    global $jmd_wiki_events;
    
    if ($format)
    {
        $out = safe_strftime($format, safe_strtotime($jmd_wiki_events->date));
    }
    else
    {
        $out = $jmd_wiki_events->date;
    }
    
    return $out;
}


class JMD_WikiEvents
{
    public $date, $uri;
    private $currentDate, $day, $limit, $reverse, $rootUri;

    public function __construct($limit, $reverse)
    {
        $this->limit = $limit;
        $this->reverse = $reverse;

        // for cache comparison
        $this->currentDate = safe_strftime('%Y-%m-%d');
        
        // use current date if used in a page template
        if ($GLOBALS['is_article_list'] && empty($GLOBALS['thisarticle']))
        {
            $this->date = $this->currentDate;
        }
        else
        {
            $this->date = posted(array(
                'format' => '%Y-%m-%d',
            ));
        }
        
        // need the day in "Month_DAY" format
        $this->day = strftime('%B_%e', strtotime($this->date));
        // for days 1-9, remove an extra space
        $this->day = str_replace(' ', '', $this->day);

        // construct URI
        $this->rootUri = 'http://en.wikipedia.org/wiki/';
        $this->uri = $this->rootUri.$this->day;
    }

    // display events
    public function display()
    {
        $events = $this->getEntry();
        if (!$events)
        {
            $events = $this->setEntry();
        }

        // store events as an array, so they can be sorted and limited
        $events = explode("\n", $events);

        if ($this->reverse == 1)
        {
            krsort($events);
        }

        if (isset($this->limit))
        {
            $events = array_slice($events, 0, intval($this->limit));
        }

        $out = '<ul>';
        foreach ($events as $e)
        {
            $out .= $e;
        }
        $out .= '</ul>';

        return $out;
    }

    // return events
    private function getEntry()
    {
        $row = getRow("SELECT last_mod, contents FROM ".safe_pfx('jmd_wiki_events')." WHERE title = '$this->day'");
        if ($row)
        {
            $diff = (str_replace('-', '', $this->currentDate)) - (str_replace('-', '', $row['last_mod']));
            // if the content is at least two months old, update
            if ($diff > 200)
            {
                return $this->setEntry($update = 1);
            }
            else
            {
                return $row['contents'];
            }
        }
    }

    // add/update and return the events from wikipedia
    private function setEntry($update = NULL)
    {
        $html = file_get_contents($this->uri);

        $eventsStart = strpos($html, '<h2>Events</h2>');
        $firstPos = strpos($html, '<li>', $eventsStart);
        $lastPos = strpos($html, '</ul>', $firstPos);
        $events = substr($html, $firstPos, ($lastPos - $firstPos));

        // Fix relative links
        $events = str_replace("/wiki/", $this->rootUri, $events);

        if ($update === NULL)
        {
            safe_insert("jmd_wiki_events", "title='$this->day', last_mod='$this->currentDate', contents='".doSlash($events)."'");
        }
        else
        {
            safe_update("jmd_wiki_events", "last_mod='$this->currentDate', contents='".doSlash($events)."'", "title='$this->day'");
        }
        
        return $events;
    }
}

# --- END PLUGIN CODE ---

?>
