<?php
/**
 * SWeTE Server: Simple Website Translation Engine
 * Copyright (C) 2012  Web Lite Translation Corp.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
$self = array_shift($argv);
echo "\n";
$dict = array(
	'XFTranslationMemory' => 'http://xataface.com/dox/modules/tm/latest/class_x_f_translation_memory.html',
	'Dataface_Record' => 'http://xataface.com/dox/core/latest/class_dataface___record.html'
);
foreach ($argv as $file){
	$contents = file_get_contents($file);
	foreach ($dict as $k=>$v){
		$contents = preg_replace('/(\b)('.preg_quote($k,'/').')(\b)/', '$1<a href="'.htmlspecialchars($v).'">'.htmlspecialchars($k).'</a>$3', $contents);
	}
	file_put_contents($file, $contents);
	echo "Updating $file\n";
}