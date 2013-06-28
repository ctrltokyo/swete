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
//require <xatajax.util.js>
(function(){

	var $ = jQuery;
	
	function sortTable(/*HTMLElement*/ table){
		
		var rows = [];
		$(table).children('tr').each(function(){ rows.push(this);}
		
		rows.sort(function(a,b){
			//var s1 = $(a).attr('data-xf-sort');
			//var s2 = $(b).attr('data-xf-sort');
			var s1 = $(a).children('td.resultListCell--webpage_url').text();
			var s2 = $(a).children('td.resultListCell--webpage_url').text();
			if ( !s1 ) s1 = '';
			if ( !s2 ) s2 = '';
			s1 = s1.toLowerCase();
			s2 = s2.toLowerCase();
			if ( s1 < s2 ) return -1;
			else if ( s2 < s1 ) return 1;
			else return 0;
			
		});
		
		$(table).children('tr').detach();
		$.each(rows, function(){
			$(table).append(this);
		});
		
		
	}
	
	function expandRow(/*HTMLElement*/ tr){
		var pageID = $(tr).children('td.resultListCell--webpage_id').text();
		var q = {
			'-action': 'list',
			'-table': 'webpages',
			'parent_id': '='+pageID
		};
		$.get(DATAFACE_SITE_HREF, q, function(res){
		
			$('tbody tr', res).insertAfter(tr);
		});
	}

})();