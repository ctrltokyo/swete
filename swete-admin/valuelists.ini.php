;<?php exit;
[languages]
	__sql__ = "select language_code, language_label from languages"

[access_levels]
1 = Read Only
2 = Translator
3 = Proofreader
4 = Manager
5 = Owner

[translation_parser_versions]
    2 = v2

[websites]
  __sql__="select website_id, website_name from websites order by website_name"
