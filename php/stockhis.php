<?php
require_once('class/ini_file.php');

// max 20 months history used
define('MAX_QUOTES_DAYS', 620);
define('BOLL_DAYS', 20);

define('SMA_SECTION', 'SMA');

function _ignoreCurrentTradingData($strDate, $sym)
{        
    $sym->SetTimeZone();
    $ymd = new NowYMD();
    if ($ymd->GetYMD() == $strDate)
    {
        if ($ymd->IsTradingHourEnd() == false)
        {   // market still trading, do not use today's data
            return true;
        }
    }
    return false;
}

// ****************************** SMA functions *******************************************************
function _estSma($arF, $iAvg)
{
    $f = 0.0;
    $iNum = $iAvg - 1;
//    $iNum = $iAvg;
    for ($i = 0; $i < $iNum; $i ++)
    {
        $f += $arF[$i];
    }
    return $f / $iNum;
}

// axx + bx + c = 0
function GetQuadraticEquationRoot($a, $b, $c)
{
    $delta = $b * $b - 4.0 * $a * $c;
    if ($delta >= 0.0)
    {
        $x1 = (0.0 - $b + sqrt($delta)) / (2.0 * $a); 
        $x2 = (0.0 - $b - sqrt($delta)) / (2.0 * $a);
//         $strDebug = sprintf('%.2f, %.2f', $x1, $x2);
//         DebugString($strDebug);
        return array($x1, $x2);
    }
    return false;
}

/*
a = (n - 4) * (n - 1)² - 4 * (n - 1)
b = (8 - 2 * (n - 4) * (n - 1)) * ∑Xn
c = (n - 4) * (∑Xn)² - 4 * ∑Xn²
*/
function _estBollingerBands($arF, $iAvg)
{
    $fSum = 0.0;
    $fQuadraticSum = 0.0;
    $iNum = $iAvg - 1;
    for ($i = 0; $i < $iNum; $i ++)
    {
        $fVal = $arF[$i];
        $fSum += $fVal;
        $fQuadraticSum += $fVal * $fVal;
    }
    $f = 1.0 * ($iAvg - 4);
    $a = $f * $iNum * $iNum - 4 * $iNum;
    $b = (8 - 2 * $f * $iNum) * $fSum;
    $c = $f * $fSum * $fSum - 4 * $fQuadraticSum;
    
    if ($ar = GetQuadraticEquationRoot($a, $b, $c))
    {
        list($x1, $x2) = $ar;
        $sigma1 = ($fSum - $iNum * $x1) / 2;
        $sigma2 = ($fSum - $iNum * $x2) / 2;
        return array($x1 - 2 * $sigma1, $x2 - 2 * $sigma2);
    }
    return false;
}

function _estNextBollingerBands($arF, $iAvg)
{
    $fSum = 0.0;
    $fQuadraticSum = 0.0;
    $iNum = $iAvg - 2;
    for ($i = 0; $i < $iNum; $i ++)
    {
        $fVal = $arF[$i];
        $fSum += $fVal;
        $fQuadraticSum += $fVal * $fVal;
    }
    $f = 1.0 * ($iAvg - 8);
    $a = $f * $iNum * $iNum - 16 * $iNum;
    $b = (32 - 2 * $f * $iNum) * $fSum;
    $c = $f * $fSum * $fSum - 16 * $fQuadraticSum;
    
    if ($ar = GetQuadraticEquationRoot($a, $b, $c))
    {
        list($x1, $x2) = $ar;
        $sigma1 = ($fSum - $iNum * $x1) / 4;
        $sigma2 = ($fSum - $iNum * $x2) / 4;
        return array($x1 - 2 * $sigma1, $x2 - 2 * $sigma2);
    }
    return false;
}

// ****************************** Private functions *******************************************************
function _isWeekEnd($strYMD, $strNextDayYMD)
{
    $ymd = new StringYMD($strYMD);
    if ($strNextDayYMD)
    {
        $next_ymd = new StringYMD($strNextDayYMD);
        if ($ymd->GetDayOfWeek() >= $next_ymd->GetDayOfWeek())     return true;
    }
    else
    { 
        if ($ymd->IsFriday())   return true;
        
        // If this Friday is not a trading day
        $now_ymd = new NowYMD();
        if ($now_ymd->IsWeekDay())
        {
            if ($ymd->GetDayOfWeek() > $now_ymd->GetDayOfWeek())     return true;
        }
        else
        {
            return true;
        }
    }
    return false;
}

function _isMonthEnd($strYMD, $strNextDayYMD)
{
    $ymd = new StringYMD($strYMD);
    if ($strNextDayYMD)
    {
        $next_ymd = new StringYMD($strNextDayYMD);
    }
    else
    {   // If the last none weekend day of a certain month is not a trading day 
        $now_ymd = new NowYMD();
        $iTick = $now_ymd->GetNextTradingDayTick();
        $next_ymd = new TickYMD($iTick);
    }
    
    if ($ymd->IsSameMonth($next_ymd))     return false;    // same month    
    return true;
}

// ****************************** StockHistory Class *******************************************************
class StockHistory
{
    var $aiNum;     // days/weeks/months 
    
    var $afSMA = array();
    var $afNext = array();
    
    var $iScore;
    var $strDate;		// 2014-11-13
    
    var $stock_ref;	// MyStockReference
    var $sql;			// StockHistorySql				
    
    function _buildNextName($strName)
    {
        return $strName.'Next';
    }
    
    function _cfg_set_SMA($cfg, $strName, $fSma, $fNext = false)
    {
        $this->afSMA[$strName] = $fSma;
        $this->afNext[$strName] = $fNext;
        
        $cfg->set_var(SMA_SECTION, $strName, strval($fSma));
        if ($fNext)
        {
        	$cfg->set_var(SMA_SECTION, $this->_buildNextName($strName), strval($fNext));
        }
    }
    
    function _cfg_get_SMA($cfg, $strName)
    {
        $this->afSMA[$strName] = floatval($cfg->read_var(SMA_SECTION, $strName));
        
        if ($str = $cfg->read_var(SMA_SECTION, $this->_buildNextName($strName)))
        {
        	$this->afNext[$strName] = floatval($str);
        }
        else
        {
        	$this->afNext[$strName] = false;
        }
    }

    function _getEMA($iDays)
    {
    	$sql = new StockEmaSql($this->GetStockId(), $iDays);
    	return $sql->GetClose($this->strDate);
    }
    
    function _cfg_get_EMA($cfg, $iDays)
    {
		if ($this->_getEMA($iDays))
		{
			$this->_cfg_get_SMA($cfg, 'EMA'.strval($iDays));
		}
    }
    
    function _loadConfigSMA($cfg)
    {
        foreach ($this->aiNum as $i)
        {
            $this->_cfg_get_SMA($cfg, 'D'.strval($i));
        }
        $this->_cfg_get_SMA($cfg, 'BOLLUP');
        $this->_cfg_get_SMA($cfg, 'BOLLDN');
        
        foreach ($this->aiNum as $i)
        {
            $this->_cfg_get_SMA($cfg, 'W'.strval($i));
        }
        
        foreach ($this->aiNum as $i)
        {
            $this->_cfg_get_SMA($cfg, 'M'.strval($i));
        }
        
        $this->_cfg_get_EMA($cfg, 50);
        $this->_cfg_get_EMA($cfg, 200);
    }
    
    function _cfg_set_EMA($cfg, $iDays)
    {
    	if ($fEma = $this->_getEMA($iDays))
		{
			$this->_cfg_set_SMA($cfg, 'EMA'.strval($iDays), $fEma);
		}
    }
    
    function _saveConfigSMA($cfg)
    {
        $afClose = array();
        $afWeeklyClose = array();
        $afMonthlyClose = array();

        $strNextDayYMD = false;
    	if ($result = $this->sql->GetFromDate($this->strDate, MAX_QUOTES_DAYS))
    	{
    		while ($history = mysql_fetch_assoc($result)) 
    		{
    			$fClose = floatval($history['adjclose']);
    			$afClose[] = $fClose;
            
    			$strYMD = $history['date'];
    			if (_isWeekEnd($strYMD, $strNextDayYMD))	$afWeeklyClose[] = $fClose;
    			if (_isMonthEnd($strYMD, $strNextDayYMD))	$afMonthlyClose[] = $fClose;
    			$strNextDayYMD = $strYMD;
    		}
    		@mysql_free_result($result);
    	}
        
        foreach ($this->aiNum as $i)
        {
            $this->_cfg_set_SMA($cfg, 'D'.strval($i), _estSma($afClose, $i), _estSma($afClose, $i - 1));
        }
        list($fUp, $fDown) = _estBollingerBands($afClose, BOLL_DAYS);
        list($fUpNext, $fDownNext) = _estNextBollingerBands($afClose, BOLL_DAYS);
        $this->_cfg_set_SMA($cfg, 'BOLLUP', $fUp, $fUpNext);
        $this->_cfg_set_SMA($cfg, 'BOLLDN', $fDown, $fDownNext);

        foreach ($this->aiNum as $i)
        {
            $this->_cfg_set_SMA($cfg, 'W'.strval($i), _estSma($afWeeklyClose, $i), _estSma($afWeeklyClose, $i - 1));
        }
            
        foreach ($this->aiNum as $i)
        {
            $this->_cfg_set_SMA($cfg, 'M'.strval($i), _estSma($afMonthlyClose, $i), _estSma($afMonthlyClose, $i - 1));
        }
        
        $this->_cfg_set_EMA($cfg, 50);
        $this->_cfg_set_EMA($cfg, 200);

        $cfg->save_data();
    }
    
    function _configSMA()
    {
        $cfg = new INIFile($this->stock_ref->strConfigName);
        $strCurDate = $this->strDate;
        if ($cfg->group_exists(SMA_SECTION))
        {
            $strDate = $cfg->read_var(SMA_SECTION, 'Date');
            if ($strDate == $strCurDate)
            {
                $this->_loadConfigSMA($cfg);
            }
            else
            {
//                $cfg->add_group(SMA_SECTION);
                $cfg->set_group(SMA_SECTION);
                $cfg->set_var(SMA_SECTION, 'Date', $strCurDate);
                $this->_saveConfigSMA($cfg);
            }
        }
        else
        {
            $cfg->add_group(SMA_SECTION);
            $cfg->set_var(SMA_SECTION, 'Date', $strCurDate);
            $this->_saveConfigSMA($cfg);
        }
    }
    
    function GetSym()
    {
        return $this->stock_ref->GetSym();
    }
    
    function GetStockSymbol()
    {
        return $this->stock_ref->GetStockSymbol();
    }

    function GetStockId()
    {
        return $this->stock_ref->GetStockId();
    }
    
    function _getStartDate()
    {
    	if ($result = $this->sql->GetAll(0, 2))
    	{
    		while ($history = mysql_fetch_assoc($result)) 
    		{
    			$strDate = $history['date'];
                if (_ignoreCurrentTradingData($strDate, $this->stock_ref->sym))
                {
                	continue;
                }
                else 
                {
                	return $strDate;
                }
            }
        }
        return false;
    }
    
    function _getScore()
    {
    	$iScore = 0;
    	$fPrice = $this->stock_ref->fPrice;
    	foreach ($this->aiNum as $i)
        {
        	$strKey = 'D'.strval($i);
            if ($fPrice > $this->afSMA[$strKey])	$iScore ++;
        }
        if ($fPrice > $this->afSMA['BOLLUP'])		$iScore ++;
        if ($fPrice > $this->afSMA['BOLLDN'])		$iScore ++;
    	return $iScore;
    }
    
    function StockHistory($ref) 
    {
        $this->stock_ref = $ref;
		$this->sql = new StockHistorySql($this->GetStockId());
        $this->aiNum = array(5, 10, 20);
		$this->strDate = $this->_getStartDate();
        $this->_configSMA();
		$this->iScore = $this->_getScore();
    }
}

function StockHistoryUpdate($arRef)
{
	foreach ($arRef as $ref)
	{
		$his = new StockHistory($ref);
	}
}

?>
