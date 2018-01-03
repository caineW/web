<?php
require_once('_stock.php');
require_once('_editmergeform.php');

function _checkStockTransaction($strGroupId, $ref)
{
    if ($result = SqlGetStockGroupItemByGroupId($strGroupId))
	{
        while ($stockgroupitem = mysql_fetch_assoc($result)) 
		{
		    if ($stockgroupitem['stock_id'] == $ref->strSqlId)
		    {
		        if (intval($stockgroupitem['record']) > 0)
		        {
		            return $stockgroupitem['id'];
		        }
		    }        
		}
		@mysql_free_result($result);
	}
	return false;
}

function _echoMyStockTransactions($strMemberId, $ref, $bChinese)
{
    $arGroup = array();
	if ($result = SqlGetStockGroupByMemberId($strMemberId)) 
	{
		while ($stockgroup = mysql_fetch_assoc($result)) 
		{
		    $strGroupId = $stockgroup['id'];
		    if ($strGroupItemId = _checkStockTransaction($strGroupId, $ref))
		    {
		        $arGroup[$strGroupId] = $strGroupItemId;
		    }
		}
		@mysql_free_result($result);
	}
	
	$iCount = count($arGroup);
	if ($iCount == 0)    return;
	foreach ($arGroup as $strGroupId => $strGroupItemId)
	{
	    $result = SqlGetStockTransactionByGroupItemId($strGroupItemId, 0, MAX_TRANSACTION_DISPLAY); 
	    EchoStockTransactionParagraph($strGroupId, $ref, $result, $bChinese);
	}
	
	if ($iCount == 1)
	{
	    StockEditTransactionForm($strGroupId, $strGroupItemId, $bChinese);
	}
	else
	{
	    StockMergeTransactionForm($arGroup, $bChinese);
	}
}

function _echoMyStock($strSymbol, $bChinese)
{
    WeixinStockPrefetchData(array($strSymbol));
    
    $sym = new StockSymbol($strSymbol);
    if ($sym->IsFundA())
    {
        $fund = WeixinStockGetFundReference($strSymbol);
        $ref = $fund->stock_ref; 
    }
    else
    {
        $ref = new MyStockReference($strSymbol);
    }
    EchoReferenceParagraph(array($ref), $bChinese);
    
    if ($sym->IsSymbolA())
    {
        if ($sym->IsFundA())
        {
            if ($fund->fPrice)      EchoSingleFundEstParagraph($fund, $bChinese);
        }
        else
        {
            if ($ref->h_ref)        EchoAHStockParagraph(array($ref), $bChinese);
        }
    }
    EchoSmaParagraph(new StockHistory($ref), false, false, false, $bChinese);
    
    if ($strMemberId = AcctIsLogin())
    {
        _echoMyStockTransactions($strMemberId, $ref, $bChinese);
    }
}

function _echoAllMyStock($bChinese)
{
}

function EchoMyStock($bChinese)
{
    if ($str = UrlGetQueryValue('symbol'))
    {
        _echoMyStock(StockGetSymbol($str), $bChinese);
    }
    else
    {
        _echoAllMyStock($bChinese);
    }

    EchoPromotionHead('', $bChinese);
}

function EchoMyStockTitle($bChinese)
{
    if ($bChinese)  echo '我的股票';
    else              echo 'My Stock ';
    EchoUrlSymbol();
}

    AcctNoAuth();

?>
