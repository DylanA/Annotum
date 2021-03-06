<?php

/**
 * @package anno
 * This file is part of the Annotum theme for WordPress
 * Built on the Carrington theme framework <http://carringtontheme.com>
 *
 * Copyright 2008-2010 Crowd Favorite, Ltd. All rights reserved. <http://crowdfavorite.com>
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 */
if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }
if (CFCT_DEBUG) { cfct_banner(__FILE__); }

global $comment;
?>

<li class="li-comment" id="li-comment-<?php comment_ID() ?>">
	<div class="div-comment" id="div-comment-<?php comment_ID(); ?>">
<?php
cfct_comment($data);
?>
	</div>
<?php
// Dropped </li> is intentional: WordPress figures out where to place the </li> so it can nest comment lists.
?>