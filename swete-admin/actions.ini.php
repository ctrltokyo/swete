;<?php exit;
;/**
; * SWeTE Server: Simple Website Translation Engine
; * Copyright (C) 2012  Web Lite Translation Corp.
; *
; * This program is free software: you can redistribute it and/or modify
; * it under the terms of the GNU General Public License as published by
; * the Free Software Foundation, either version 3 of the License, or
; * (at your option) any later version.
; *
; * This program is distributed in the hope that it will be useful,
; * but WITHOUT ANY WARRANTY; without even the implied warranty of
; * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
; * GNU General Public License for more details.
; *
; * You should have received a copy of the GNU General Public License
; * along with this program.  If not, see <http://www.gnu.org/licenses/>.
; */
[import > import]
	category=""

[swete_import_webpages]
	category=table_actions
	url="{$this->url('-action=swete_import_webpages')}"
	label="Refresh Site"
	order=10
	condition="$app->_conf['enable_static']"

[set_translation_status > set_translation_status]
	condition="false"

[invalidate_translations > invalidate_translations]
	condition="false"

[swete_view_site]
	subcategory=view_site_actions
	label="View Site"
	category=record_actions
	condition="$query['-table'] == 'websites'"


[swete_goto_proxy_site]
	url="//{$record->val('host')}{$record->val('base_path')}"
	target="_blank"
	label="{$record->display('target_language')} Site"
	category=view_site_actions
	label_condition="$record and $record->table()->tablename=='websites'"
	condition="$record and $record->table()->tablename=='websites'"

[swete_goto_source_site]
	url="{$record->val('website_url')}"
	target="_blank"

	label="{$record->display('source_language')} Site"
	category=view_site_actions
	label_condition="$record and $record->table()->tablename=='websites'"
	condition="$record and $record->table()->tablename=='websites'"



[new > new]
	condition="$query['-table'] != 'webpages'"

[swete_message_delete]
	url="{$this->url('-action=swete_message_delete')}"
	label="Delete a message"
    category=swete_message_actions

[swete_message_add]
	url="{$this->url('-action=swete_message_add')}"
	label="Add a message"
    category=swete_message_actions

[swete_message_read]
	url="{$this->url('-action=swete_message_read')}"
	label="Read a message"
    category=swete_message_actions

[swete_add_selected_webpages_to_job]
    label="Submit for Translation"
    category=result_list_actions
    condition="$this->_conf['enable_jobs'] and $query['-table'] == 'webpages'"
    class=swete_add_selected_webpages_to_job


[swete_add_webpage_to_job]
	label="Submit for Translation"
    category=record_actions
    condition="$this->_conf['enable_jobs'] and $query['-table'] == 'webpages'"
    class=swete_add_webpage_to_job

[swete_add_selected_strings_to_job]
	label="Submit for Translation"
	category=result_list_actions
	condition="$this->_conf['enable_jobs'] and $query['-table'] == 'translation_miss_log'"
	class=swete_add_selected_strings_to_job

[swete_add_string_to_job]
	label="Submit for Translation"
	category=record_actions
	condition="$this->_conf['enable_jobs'] and $query['-table'] == 'translation_miss_log'"
	class=swete_add_string_to_job

[swete_compile_job]
	label="Compile"
	category=record_actions
	condition="$record and ($query['-table'] == 'jobs')&&($record->val('compiled') == 0)"
	class=swete_compile_job

[swete_remove_webpage_from_job]
	url="{$this->url('-action=swete_remove_webpage_from_job')}"
	label="Remove Webpage"
    category=swete-job-actions

[swete_approve_job]
	label="Approve"
	category=record_actions
	condition="$record and ($query['-table'] == 'jobs')&&($record->val('compiled') == 1)&&($record->val('job_status') != 5)"
	class=swete_approve_job

[swete_review_translations]
	label="Review Translations"
	category=record_tabs
	mode=browse
	condition="$record and ($query['-table'] == 'jobs')"
	class=swete_review_translations
	url="{$this->url('-action=swete_review_translations')}"



[swete_approve_pages]
	label="Approve"
	category=result_list_actions
	condition="$query['-table'] == 'webpages'"
	class="approve-selected-pages-action"
	order=-1

[swete_assign_job]
	label="Assign"
	category=record_actions
	condition="$record and ($query['-table'] == 'jobs')"
	class=swete_assign_job

[batch_google_translate]
	class="batch-google-translate"
	category=result_list_actions
	condition="$record and in_array($record->table()->tablename, array('translation_miss_log','swete_strings'))"
	label="Google Translate"
	url="#"

[swete_capture_strings]
	category=record_actions
	label="Capture Strings"
	condition="$record and $record->table()->tablename=='websites'"
	url="{$site_href}?-action=swete_capture_strings&-table=websites&website_id={$record->val('website_id')}"

[dashboard]
	permission=view
	
[dashboard_site_stats]
    permission=view

[export_found_strings_as_xliff]
	label="Export found strings as XLIFF"
	category=list_export_actions
	url="{$this->url('-action=swete_export_found_strings_as_xliff')}"
 	condition="in_array($query['-table'], array('translation_miss_log','swete_strings'))"
 	permission=export_csv

[export_selected_strings_as_xliff > export_found_strings_as_xliff]
	label="Export selected strings as XLIFF"
	class="selected-action"

[swete_import_translations]
    category=table_actions
    condition="in_array($query['-table'], array('translation_miss_log','swete_strings'))"
    permission="import"
    url="{$this->url('-action=swete_import_translations')}"
    label="Import Translations"
    order=10

[copy_site]
    category=record_actions
    condition="$query['-table']=='websites'"
    url="{$this->url('-action=new&-table=website_copy_form&website_id')}&website_id={$record->val('website_id')}"
    url_condition="$record"
    description="Copy this website profile"

[swete_add_string_to_blacklist]
    class="selected-action"
    label="Blacklist"
    description="Blacklist selected strings"
    condition="$query['-table'] == 'swete_strings'"
    category=result_list_actions
    permission=translate
    url="{$this->url('-action=swete_add_string_to_blacklist')}"


[swete_clear_cache]
    category=management_actions
    url="javascript:(function(){window.sweteClearCache(); return false;})();"
    permission=manage
    label="Clear Live Cache"
    description="Clears the SWeTE Live Cache"
