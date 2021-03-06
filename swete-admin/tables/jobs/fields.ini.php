;<?php exit;
[website_id]
	widget:label = "Site"
	widget:type=select
	vocabulary=websites
	order=-100
	filter = 1
	
[webpages]
	widget:label = "Pages"
	transient=1
	relationship=webpages
	widget:type=grid
	widget:columns="webpage_id"
	order=10
	
[date_created]
	widget:type=hidden
	timestamp=insert
	
[job_status]
	widget:type=hidden
	vocabulary=statuses
	filter = 1
	

[translation_memory_id]
	widget:type=hidden
	visibility:list=hidden
	visibility:browse=hidden
	visibility:find=hidden
	
[source_language]
	widget:type=hidden
	vocabulary=languages
	
[destination_language]
	widget:type=hidden
	vocabulary=languages
	
[posted_by]
	widget:type=hidden
	filter = 1
	
[assigned_to]
	widget:type=lookup
	widget:table=users
	filter = 1
	
[access_level]
	widget:label ="Access Level"
	vocabulary=access_levels
	
[compiled]
	widget:type=hidden
	filter = 1
	
[word_count]
	widget:type=hidden