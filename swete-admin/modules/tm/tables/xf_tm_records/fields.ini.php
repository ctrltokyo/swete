;<?php exit;
__sql__ = "select 
	r.*,
	tm.source_language,
	tm.destination_language
	from
		xf_tm_records r 
		left join xf_tm_translation_memories tm on r.translation_memory_id=tm.translation_memory_id
	"