<?php 
require('php/_chinaindex.php');

function GetChinaIndexRelated($sym)
{
	$str = GetHuaXiaOfficialLink($sym->GetDigitA());
	$str .= ' '.GetChinaIndexLinks($sym);
	$str .= GetHuaXiaSoftwareLinks();
	return $str;
}

require('/php/ui/_dispcn.php');
?>
