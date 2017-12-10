<?php
require_once('gb2312.php');
require_once('stock.php');
require_once('mysqlstockhistory.php');
require_once('sql/sqlstock.php');

// ****************************** StockReference class related *******************************************************

class MyStockReference extends StockReference
{
    public static $iDataSource = STOCK_DATA_SINA;

    var $strSqlId = false;      // ID in mysql database
    var $fFactor;

    var $h_ref = false;          // H stock MyStockReference
    
    function _loadFactor()
    {
        if ($fVal = SqlGetStockCalibrationFactor($this->strSqlId))
        {
            $this->fFactor = $fVal;
        }
        else
        {
            $this->fFactor = 1.0;
        }
        return $this->fFactor;
    }
    
    // ETF Factor functions
    function EstEtf($fVal)
    {
        return $fVal / $this->fFactor;
    }
    
    function EstByEtf($fEtf)
    {
        return $fEtf * $this->fFactor;
    }
    
    function LoadEtfFactor($etf_ref)
    {
        if ($this->AdjustEtfFactor($etf_ref) == false)
        {
            return $this->_loadFactor();
        }
        return $this->fFactor;
    }

    function AdjustEtfFactor($etf_ref)
    {
        if ($this->CheckAdjustFactorTime($etf_ref))
        {
            $this->fFactor = $this->fPrice / $etf_ref->fPrice;
            $this->InsertStockCalibration($etf_ref);
            return true;
        }
        return false;
    }

    function InsertStockCalibration($etf_ref)
    {
        return SqlInsertStockCalibration($this->strSqlId, $etf_ref->GetStockSymbol(), $this->strPrice, $etf_ref->strPrice, $this->fFactor, $etf_ref->GetDateTime());
    }

    // Future Factor functions
    function EstByFuture($fEtf, $fCNY)
    {
        return $fEtf * $fCNY / $this->fFactor;
    }
    
    function LoadFutureFactor($future_ref, $strForexSqlId)
    {
        if ($this->AdjustFutureFactor($future_ref, $strForexSqlId) == false)
        {
            $this->_loadFactor();
        }
        return $this->fFactor;
    }
    
    function AdjustFutureFactor($future_ref, $strForexSqlId)
    {
        if ($this->bHasData == false)    return false;
        
        $fCNY = SqlGetForexCloseHistory($strForexSqlId, $this->strDate);
        if ($fCNY)
        {
            if ($this->CheckAdjustFactorTime($future_ref))
            {
                $this->fFactor = $future_ref->fPrice * $fCNY / $this->fPrice;
                $this->InsertStockCalibration($future_ref);
                return true;
            }
        }
        return false;
    }
    
    function _loadSqlId($strSqlName, $bConvertGB2312)
    {
        if (($strSqlId = SqlGetStockId($strSqlName)) === false)
        {
            if ($this->bHasData)
            {
                if ($bConvertGB2312)
                {
                    $strEnglish = FromGB2312ToUTF8($this->strName);
                    $strChinese = FromGB2312ToUTF8($this->strChineseName);
                }
                else
                {
                    $strEnglish = $this->strName;
                    $strChinese = $this->strChineseName;
                }
                SqlInsertStock($strSqlName, $strEnglish, $strChinese);
                $strSqlId = SqlGetStockId($strSqlName);
            }
        }
        return $strSqlId;
    }
    
    function _invalidHistoryData($str)
    {
//        if (empty($str))    return true;
        if ($str == 'N/A')   return true;
        if (FloatNotZero(floatval($str)) == false)  return true;
        return false;
    }
    
    function _updateStockHistory()
    {
        if ($this->bHasData == false)   return false;
        
        $strStockId = $this->strSqlId;
        $strDate = $this->strDate;
        $strOpen = $this->strOpen;
        $strHigh = $this->strHigh;
        $strLow = $this->strLow;
        $strClose = $this->strPrice;
        $strVolume = $this->strVolume;
        if ($history = SqlGetStockHistoryByDate($strStockId, $strDate))
        {
//            if ($this->_invalidHistoryData($strOpen))   return false;
//            if ($this->_invalidHistoryData($strHigh))   return false;
//            if ($this->_invalidHistoryData($strLow))    return false;
            if ($this->_invalidHistoryData($strClose))  return false;
            return SqlUpdateStockHistory($history['id'], $strOpen, $strHigh, $strLow, $strClose, $strVolume, $strClose);
        }
        else
        {
            return SqlInsertStockHistory($strStockId, $strDate, $strOpen, $strHigh, $strLow, $strClose, $strVolume, $strClose);
        }
        return false;
    }
    
    function GetStockId()
    {
        return $this->strSqlId;
    }
    
    // constructor 
    function MyStockReference($strSymbol) 
    {
        $bConvertGB2312 = false;
        $strSqlName = $strSymbol;
        $this->_newStockSymbol($strSymbol);
        if (self::$iDataSource == STOCK_DATA_SINA)
        {
            if ($strSinaSymbol = $this->sym->GetSinaSymbol())
            {
                $this->LoadSinaData($strSinaSymbol);
                $bConvertGB2312 = true;     // Sina name is GB2312 coded
            }
            else
            {
                if ($strGoogleSymbol = $this->sym->GetGoogleSymbol())
                {
                    $this->LoadGoogleData($strGoogleSymbol);
                }
                else
                {
                    $this->LoadYahooData();
                }
            }
        }
        else if (self::$iDataSource == STOCK_DATA_YAHOO)
        {
            $this->LoadYahooData();
        }
        else if (self::$iDataSource == FUTURE_DATA_SINA)
        {
            $strSqlName = FutureGetSinaSymbol($strSymbol);
            $this->LoadSinaFutureData($strSymbol);
            $bConvertGB2312 = true;     // Sina name is GB2312 coded
        }
        
        parent::StockReference($strSymbol);
        if ($this->strSqlId = $this->_loadSqlId($strSqlName, $bConvertGB2312))
        {
            $this->_updateStockHistory();
            $this->strDescription = SqlGetStockDescription($strSqlName);
        }
        
        if ($strSymbolH = AhGetSymbol($strSymbol))
        {
            $this->h_ref = new MyStockReference($strSymbolH);
        }
    }
}

function _getEtfLeverageRatio($strSymbol)
{
    if ($strSymbol == 'SH')         return -1.0;
    else if ($strSymbol == 'VXX')  return 0.5;      // compare with UVXY
    else if ($strSymbol == 'SVXY')  return -0.5;    // compare with UVXY
    else if ($strSymbol == 'DGP' || $strSymbol == 'AGQ' || $strSymbol == 'UCO')   return 2.0;
    else if ($strSymbol == 'SDS' || $strSymbol == 'DZZ' || $strSymbol == 'ZSL' || $strSymbol == 'SCO')  return -2.0;
    else if ($strSymbol == 'GUSH' || $strSymbol == 'UWT' || $strSymbol == 'UPRO' || $strSymbol == 'UGAZ')  return 3.0;
    else if ($strSymbol == 'DRIP' || $strSymbol == 'DWT' || $strSymbol == 'SPXU' || $strSymbol == 'DGAZ')  return -3.0;
    else if ($strSymbol == 'WB')  return 1.46;      // compare with SINA
    else 
        return 1.0;
}

class MyLeverageReference extends MyStockReference
{
    var $fRatio;
    
    // constructor 
    function MyLeverageReference($strSymbol) 
    {
        $this->fRatio = _getEtfLeverageRatio($strSymbol);
        parent::MyStockReference($strSymbol);
    }

    function EstByEtf1x($fEtf1x, $ref_1x)
    {
        $fGain1x = ($fEtf1x / $ref_1x->fPrevPrice) - 1.0;
        return (1.0 + $this->fRatio * $fGain1x) * $this->fPrevPrice; 
    }
    
    function GetEstByEtf1xDisplay($fEtf1x, $ref_1x)
    {
        $fVal = $this->EstByEtf1x($fEtf1x, $ref_1x);
        return $this->GetPriceDisplay($fVal);
    }
}

class MyYahooStockReference extends MyStockReference
{
    // constructor 
    function MyYahooStockReference($strSymbol) 
    {
        $iBackup = parent::$iDataSource;
        parent::$iDataSource = STOCK_DATA_YAHOO;
        parent::MyStockReference($strSymbol);
        parent::$iDataSource = $iBackup;
    }
}

class MyFutureReference extends MyStockReference
{
    // constructor 
    function MyFutureReference($strSymbol) 
    {
        $iBackup = parent::$iDataSource;
        parent::$iDataSource = FUTURE_DATA_SINA;
        parent::MyStockReference($strSymbol);
        parent::$iDataSource = $iBackup;
    }
}

// ****************************** FundReference class related *******************************************************

define ('FUND_POSITION_RATIO', 0.95);
define ('FUND_EMPTY_NET_VALUE', '0');

class MyFundReference extends FundReference
{
    var $stock_ref = false;     // MyStockReference
    var $future_ref = false;
    var $future_etf_ref = false;
    
    var $strOfficialDate = false;
    
    var $fFactor;
    
    var $strForexSymbol;
    var $strForexSqlId;
    
    function SetForex($strForex)
    {
        $this->strForexSymbol = $strForex;
        $this->strForexSqlId = SqlGetStockId($this->strForexSymbol);
    }

    function GetForexNow()
    {
        $history = SqlGetForexHistoryNow($this->strForexSqlId);
        return floatval($history['close']);
    }
    
    // Update database
    function UpdateEstNetValue()
    {
        $strSqlId = $this->GetStockId();
        $strDate = $this->est_ref->strDate;
        list($strDummy, $strTime) = explodeDebugDateTime();
        $strPrice = strval($this->fPrice);
        if ($history = SqlGetFundHistoryByDate($strSqlId, $strDate))
        {
            if ($history['netvalue'] == FUND_EMPTY_NET_VALUE)
            {   // Only update when official net value is not ready
                SqlUpdateFundHistory($history['id'], FUND_EMPTY_NET_VALUE, $strPrice, $strTime);
            }
        }
        else
        {
            SqlInsertFundHistory($strSqlId, $strDate, FUND_EMPTY_NET_VALUE, $strPrice, $strTime);
        }
    }

    function UpdateOfficialNetValue()
    {
        $strSqlId = $this->GetStockId();
        $strDate = $this->strDate;
        $strNetValue = $this->strPrevPrice;
        if ($history = SqlGetFundHistoryByDate($strSqlId, $strDate))
        {
            if ($history['netvalue'] == FUND_EMPTY_NET_VALUE)
            {
                SqlUpdateFundHistory($history['id'], $strNetValue, $history['estimated'], $history['time']);
            }
            else
            {
                return false;
            }
        }
        else
        {
            SqlInsertFundHistory($strSqlId, $strDate, $strNetValue, '0', '0');
        }
        return true;
    }

    function InsertFundCalibration($est_ref, $strEstPrice)
    {
        return SqlInsertStockCalibration($this->GetStockId(), $est_ref->GetStockSymbol(), $this->strPrevPrice, $strEstPrice, $this->fFactor, DebugGetTimeDisplay());
    }

    function GetStockSymbol()
    {
        if ($this->stock_ref)
        {
            return $this->stock_ref->GetStockSymbol();
        }
        return false;
    }

    function GetStockId()
    {
        if ($this->stock_ref)
        {
            return $this->stock_ref->GetStockId();
        }
        return false;
    }
    
    // constructor 
    function MyFundReference($strSymbol)
    {
        if (StockFundFromCN($strSymbol))
        {
            $this->stock_ref = new MyStockReference($strSymbol);
        }
        parent::FundReference($strSymbol);
        if ($fVal = SqlGetStockCalibrationFactor($this->GetStockId()))
        {
            $this->fFactor = $fVal; 
        }
        else
        {
            $this->fFactor = 1.0; 
        }
    }

    function AdjustPosition($fVal)
    {
        return $fVal * FUND_POSITION_RATIO + $this->fPrevPrice * (1.0 - FUND_POSITION_RATIO);
    }
}

function FundUpdateHistory($ref)
{
    $strId = $ref->GetStockId();
    $strDate = $ref->est_ref->strDate;
    $strPrice = strval($ref->fPrice);
    if ($ref->strDate == $strDate)
    {
        $strNetValue = $ref->strPrevPrice;
    }
    else
    {
        $strNetValue = '0';
        if ($history = SqlGetFundHistoryByDate($strId, $ref->strDate))
        {
            if ($ref->strPrevPrice != $history['netvalue'])
            {
                SqlUpdateFundHistory($history['id'], $ref->strPrevPrice, $history['estimated'], $history['time']);
            }
        }
    }
    
    list($strDummy, $strTime) = explodeDebugDateTime();
    if ($history = SqlGetFundHistoryByDate($strId, $strDate))
    {
        if ($strNetValue == '0')
        {
            SqlUpdateFundHistory($history['id'], $strNetValue, $strPrice, $strTime);
        }
        else if ($strNetValue != $history['netvalue'])
        {
            SqlUpdateFundHistory($history['id'], $strNetValue, $history['estimated'], $history['time']);
        }
    }
    else
    {
        SqlInsertFundHistory($strId, $strDate, $strNetValue, $strPrice, $strTime);
    }
}

// ****************************** ForexReference class related *******************************************************

function ForexUpdateHistory($ref)
{
    if (FloatNotZero(floatval($ref->strOpen)) == false)
    {
        $ref->EmptyFile();
        return;
    }
    
    $strId = SqlGetStockId($ref->GetStockSymbol());
    if (SqlGetForexHistory($strId, $ref->strDate) === false)
    {
        SqlInsertForexHistory($strId, $ref->strDate, $ref->strPrice);
    }    
}

// ****************************** StockTransaction class related *******************************************************

class MyStockTransaction extends StockTransaction
{
    var $ref;                       // MyStockReference
    var $strStockGroupItemId;
    
    // constructor 
    function MyStockTransaction($ref, $strStockGroupId) 
    {
        $this->ref = $ref;
        if ($strStockGroupId)
        {
            if ($ref)   $this->strStockGroupItemId = SqlGetStockGroupItemId($strStockGroupId, $ref->strSqlId);
        }
        parent::StockTransaction();
    }

    function GetStockSymbol()
    {
        if ($this->ref)
        {
            return $this->ref->GetStockSymbol();
        }
        return false;
    }
    
    function GetAvgCostDisplay()
    {
        if ($this->ref)     return $this->ref->GetPriceDisplay($this->GetAvgCost());
        return '';
    }
    
    function GetValue()
    {
        if ($this->ref)     return $this->iTotalShares * $this->ref->fPrice;
        return 0.0;
    }

    function GetValueDisplay()
    {
        return GetNumberDisplay($this->GetValue());
    }
    
    function GetProfit()
    {
        return $this->GetValue() - $this->fTotalCost;
    }
    
    function GetProfitDisplay()
    {
        return GetNumberDisplay($this->GetProfit());
    }
}

// ****************************** StockGroup class related *******************************************************

class MyStockGroup extends StockGroup
{
    var $strGroupId;
    
    var $arStockTransaction = array();
    
    var $arbi_trans;
    var $bCountArbitrage;
    
    function GetStockTransactionByStockGroupItemId($strStockGroupItemId)
    {
        foreach ($this->arStockTransaction as $trans)
        {
            if ($trans->strStockGroupItemId == $strStockGroupItemId)     return $trans;
        }
        return false;
    }
    
    function GetStockTransactionByStockId($strStockId)
    {
        foreach ($this->arStockTransaction as $trans)
        {
            if ($trans->ref->strSqlId == $strStockId)     return $trans;
        }
        return false;
    }
    
    function GetStockTransactionBySymbol($strSymbol)
    {
        foreach ($this->arStockTransaction as $trans)
        {
            if ($trans->GetStockSymbol() == $strSymbol)   return $trans;
        }
        return false;
    }
    
    function GetStockTransactionCN()
    {
        foreach ($this->arStockTransaction as $trans)
        {
            if ($trans->ref->sym->IsSymbolA())     return $trans;
        }
        return false;
    }

    function GetStockTransactionHK()
    {
        foreach ($this->arStockTransaction as $trans)
        {
            if ($trans->ref->sym->IsSymbolH())     return $trans;
        }
        return false;
    }
    
    function GetStockTransactionUS()
    {
        foreach ($this->arStockTransaction as $trans)
        {
            if ($trans->ref->sym->IsSymbolUS())     return $trans;
        }
        return false;
    }
    
    function _addTransaction($ref)
    {
        $this->arStockTransaction[] = new MyStockTransaction($ref, $this->strGroupId);
    }
    
    function _checkSymbol($strSymbol)
    {
        if ($this->GetStockTransactionBySymbol($strSymbol))  return;
        
        $this->_addTransaction(new MyStockReference($strSymbol));
    }
        
    function AddTransaction($strSymbol, $iShares, $fCost)
    {
        $this->_checkSymbol($strSymbol);
        foreach ($this->arStockTransaction as $trans)
        {
            if ($trans->GetStockSymbol() == $strSymbol)
            {
                $trans->AddTransaction($iShares, $fCost);
                break;
            }
        }
    }

    function SetValue($strSymbol, $iTotalRecords, $iTotalShares, $fTotalCost)
    {
        $this->_checkSymbol($strSymbol);
        foreach ($this->arStockTransaction as $trans)
        {
            if ($trans->GetStockSymbol() == $strSymbol)
            {
                $trans->SetValue($iTotalRecords, $iTotalShares, $fTotalCost);
                $this->OnStockTransaction($trans);
                break;
            }
        }
    }

    function GetTotalRecords()
    {
        $iTotal = 0;
        foreach ($this->arStockTransaction as $trans)
        {
            $iTotal += $trans->iTotalRecords;
        }
        return $iTotal;
    }
    
    function _checkArbitrage($strSymbol)
    {
        if ($this->arbi_trans)
        {
            if ($this->arbi_trans->GetStockSymbol() != $strSymbol)
            {
                $this->bCountArbitrage = false;
            }
        }
        else
        {
            $trans = $this->GetStockTransactionBySymbol($strSymbol);
            if ($trans)
            {
                $this->arbi_trans = new MyStockTransaction($trans->ref, $this->strGroupId);
                $this->bCountArbitrage = true;
            }
        }
    }
    
    function _onArbitrageTransaction($strSymbol, $transaction)
    {
        $this->_checkArbitrage($strSymbol);
        if ($this->bCountArbitrage)
        {
            AddSqlTransaction($this->arbi_trans, $transaction);
            return true;
        }
        return false;
    }
    
    function OnArbitrage()
    {
        $strGroupId = $this->strGroupId;
        if ($result = SqlGetStockTransactionByGroupId($strGroupId, 0, 0)) 
        {   
            $arGroupItemSymbol = SqlGetStockGroupItemSymbolArray($strGroupId);
            while ($transaction = mysql_fetch_assoc($result)) 
            {
                $strSymbol = $arGroupItemSymbol[$transaction['groupitem_id']];
                if ($this->_onArbitrageTransaction($strSymbol, $transaction) == false)  break;
            }
            @mysql_free_result($result);
        }
    }
    
    // constructor 
    function MyStockGroup($strGroupId, $arRef) 
    {
        $this->strGroupId = $strGroupId;
        $this->arbi_trans = false;
        
        foreach ($arRef as $ref)
        {
            $this->_addTransaction($ref);
        }
        parent::StockGroup();
        
        if ($result = SqlGetStockGroupItemByGroupId($strGroupId)) 
        {   
            while ($groupitem = mysql_fetch_assoc($result)) 
            {
                if (intval($groupitem['record']) > 0)
                {
                    $this->SetValue(SqlGetStockSymbol($groupitem['stock_id']), intval($groupitem['record']), intval($groupitem['quantity']), floatval($groupitem['cost']));
                }
            }
            @mysql_free_result($result);
        }
    }
}

// ****************************** General functions related with Sql and stock *******************************************************

function _getHistoryQuotesYMD($str)
{
    $arLines = explode("\n", $str, 3);
    $arWords = explode(',', $arLines[1], 2);
//    return explode('-', $arWords[0]);
    return $arWords[0];
}

function _getPastQuotes($sym, $strFileName)
{
    if (($str = IsNewDailyQuotes($sym, $strFileName, false, _getHistoryQuotesYMD)) === false)
    {
        $str = GetYahooPastQuotes($sym->GetYahooSymbol(), MAX_QUOTES_DAYS);
        file_put_contents($strFileName, $str);
    }
    return $str;
}

function _sqlMergeStockHistory($strStockId, $strDate, $strOpen, $strHigh, $strLow, $strClose, $strVolume, $strAdjClose)
{
    if ($history = SqlGetStockHistoryByDate($strStockId, $strDate))
    {
        SqlUpdateStockHistory($history['id'], $strOpen, $strHigh, $strLow, $strClose, $strVolume, $strAdjClose);
    }
    else
    {
        SqlInsertStockHistory($strStockId, $strDate, $strOpen, $strHigh, $strLow, $strClose, $strVolume, $strAdjClose);
    }
}

function _oldUpdateYahooHistory($strStockId, $sym)
{
    $strSymbol = $sym->strSymbol;
    $strFileName = DebugGetYahooHistoryFileName($strSymbol);
    $str = _getPastQuotes($sym, $strFileName);
    if (IsYahooStrError($str))
    {
        DebugString('IsYahooStrError returned ture with symbol - '.$strSymbol);
        return;
    }

    DebugString('StockUpdateYahooHistory with symbol - '.$strSymbol);
    $arYahoo = explode("\n", $str);
    foreach ($arYahoo as $strLine)
    {
        $ar = explode(',', $strLine);
//        DebugString($ar[0].' '.$ar[1].' '.$ar[2].' '.$ar[3].' '.$ar[4].' '.$ar[5].' '.$ar[6]);
        $strDate = $ar[0];
        if ((!empty($strDate)) && ($strDate != 'Date'))
        {
            _sqlMergeStockHistory($strStockId, $strDate, $ar[1], $ar[2], $ar[3], $ar[4], $ar[5], $ar[6]);
/*            if ($history = SqlGetStockHistoryByDate($strStockId, $strDate))
            {
                SqlUpdateStockHistory($history['id'], $ar[1], $ar[2], $ar[3], $ar[4], $ar[5], $ar[6]);
            }
            else
            {
                SqlInsertStockHistory($strStockId, $strDate, $ar[1], $ar[2], $ar[3], $ar[4], $ar[5], $ar[6]);
            }*/
        }
    }
}

function _webUpdateYahooHistory($strStockId, $sym)
{
    $strSymbol = $sym->GetYahooSymbol();
    $sym->SetTimeZone();
    $iTime = time();
    
    $iMax = 100;
    $iMaxSeconds = $iMax * SECONDS_IN_DAY;
    for ($k = 0; $k < MAX_QUOTES_DAYS; $k += $iMax)
    {
        $str = YahooGetStockHistory($strSymbol, $iTime - $iMaxSeconds, $iTime);
        $iTime -= $iMaxSeconds;

        $arMatch = preg_match_yahoo_history($str);
        $iVal = count($arMatch);
        if ($iVal < $iMax / 2)
        {
            DebugString('_webUpdateYahooHistory error: '.$strSymbol);
            DebugVal($iVal);
        }
        
        for ($j = 0; $j < $iVal; $j ++)
        {
            $strDate = dateYMD(strtotime($arMatch[$j][1]));
            $ar = array();
            $str = $strDate;
            for ($i = 0; $i < 6; $i ++)
            {
                $strNoComma = str_replace(',', '', $arMatch[$j][$i + 2]); 
                $ar[] = $strNoComma;
                $str .= ' '.$strNoComma; 
            }
//            DebugString($str);
            _sqlMergeStockHistory($strStockId, $strDate, $ar[0], $ar[1], $ar[2], $ar[3], $ar[5], $ar[4]);
       }
    }
}

function StockUpdateYahooHistory($strStockId, $strSymbol)
{
    unlinkEmptyFile(DebugGetConfigFileName($strSymbol));
    
    $sym = new StockSymbol($strSymbol);
//    _oldUpdateYahooHistory($strStockId, $sym);
    _webUpdateYahooHistory($strStockId, $sym);
    
    if ($sym->IsSymbolA() || $sym->IsSymbolH())
    {   // Yahoo has wrong Chinese and Hongkong holiday record with '0' volume 
        if ($sym->IsIndex() == false)
        {
            SqlDeleteStockHistoryWithZeroVolume($strStockId);
        }
    }
}

function StockGroupItemTransactionUpdate($strStockGroupItemId)
{
    $trans = new StockTransaction();
    if ($result = SqlGetStockTransactionByGroupItemId($strStockGroupItemId, 0, 0)) 
    {
        while ($transaction = mysql_fetch_assoc($result)) 
        {
            AddSqlTransaction($trans, $transaction);
        }
        @mysql_free_result($result);
    }
    SqlUpdateStockGroupItem($strStockGroupItemId, strval($trans->iTotalShares), strval($trans->fTotalCost), strval($trans->iTotalRecords));
}

/*
function StockGroupItemUpdateAll()
{
    if ($result = SqlGetTableData('stockgroupitem', false, false, false)) 
    {
        while ($item = mysql_fetch_assoc($result)) 
        {
            StockGroupItemTransactionUpdate($item['id']);
        }
        @mysql_free_result($result);
    }
}
*/

function StockGroupItemUpdate($strGroupItemId)
{
    $groupitem = SqlGetStockGroupItemById($strGroupItemId);
	if ($result = SqlGetStockGroupItemByGroupId($groupitem['group_id']))
	{
		while ($stockgroupitem = mysql_fetch_assoc($result)) 
		{
		    StockGroupItemTransactionUpdate($stockgroupitem['id']);
		}
		@mysql_free_result($result);
	}
}

function StockGetIdSymbolArray($strSymbols)
{
	$arIdSymbol = array();
    $arSymbol = StockGetSymbolArray($strSymbols);
	foreach ($arSymbol as $strSymbol)
	{
	    $strStockId = SqlGetStockId($strSymbol);
	    if ($strStockId == false)
	    {
            $ref = new MyStockReference($strSymbol);
            $strStockId = $ref->strSqlId;
	    }
	    $arIdSymbol[$strStockId] = $strSymbol; 
	}
	return $arIdSymbol;
}

function StockInsertGroup($strMemberId, $strGroupName, $strStocks)
{
    SqlInsertStockGroup($strMemberId, $strGroupName);
    $strGroupId = SqlGetStockGroupId($strGroupName, $strMemberId);
    
    if ($strGroupId)
    {
        $arIdSymbol = StockGetIdSymbolArray($strStocks);
        foreach ($arIdSymbol as $strStockId => $strSymbol)
        {
	        SqlInsertStockGroupItem($strGroupId, $strStockId);
        }
    }
    
    return $strGroupId;
}

?>