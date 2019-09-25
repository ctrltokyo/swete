;<?php exit;
__sql__ = "select tml.*,
	s.num_words,
	t.normalized_translation_value,
	if(tml.webpage_id is null,hrl.proxy_request_url, concat(ws.website_url,w.webpage_url)) as request_url,
        tm.translation_memory_uuid
	from translation_miss_log tml
        left join xf_tm_translation_memories tm on tml.translation_memory_id=tm.translation_memory_id
	left join xf_tm_strings s on tml.string_id=s.string_id
	left join xf_tm_translation_memory_strings tms on tms.string_id=tml.string_id and tms.translation_memory_id=tml.translation_memory_id
	left join xf_tm_translations t on tms.current_translation_id=t.translation_id
	left join http_request_log hrl on tml.http_request_log_id=hrl.http_request_log_id
	left join webpages w on tml.webpage_id=w.webpage_id
	left join websites ws on tml.website_id=ws.website_id
	"
[request_url]
    order=-50

[translation_miss_log_id]
	visibility:list=hidden
	visibility:find=hidden
	visibility:csv=hidden

[webpage_refresh_log_id]
	visibility:list=hidden
	visibility:find=hidden
	visibility:csv=hidden
	
[http_request_log_id]
	visibility:list=hidden
	visibility:find=hidden
	visibility:csv=hidden
	
[normalized_string]
	visibility:list=hidden
	visibility:find=hidden
	title=1
	order=-100

	
[string]
	order=-100
	visibility:csv=hidden
	
[encoded_string]
	visibility:list=hidden
	visibility:find=hidden
	visibility:csv=hidden
	
[string_hash]
	visibility:list=hidden
	visibility:find=hidden
	order=-91
	visibility:csv=hidden
	
[webpage_id]
	visibility:list=hidden
	visibility:find=hidden
	order=-93
	
[source_language]
	visibility:list=hidden
	visibility:find=hidden
	order=-95
	
[destination_language]
	visibility:list=hidden
	visibility:find=hidden
	order=-94
	
[string_id]
	visibility:list=hidden
	visibility:find=hidden
	order=-96
	
	
[translation_memory_id]
	visibility:list=hidden
        visibility:csv=hidden
	order=-97

[translation_memory_uuid]
        visibility:list=hidden
        order=-96
        
	
[normalized_translation_value]
	order=-99
	widget:label="Current Translation"
	
[num_words]
	widget:label="Num Words"
	order=-98
	
[website_id]
	filter=1
	vocabulary=websites
	order=-10