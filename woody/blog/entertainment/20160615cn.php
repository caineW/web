<?php require_once('php/_entertainment.php'); ?>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title>东方财富人民币美元中间价汇率实时数据接口的字段意义</title>
<meta name="description" content="记录和分析东方财富人民币美元中间价(http://hq2gjqh.eastmoney.com/EM_Futures2010NumericApplication/Index.aspx?type=z&ids=usdcny0)汇率实时数据接口的字段意义.">
<link href="../../../common/style.css" rel="stylesheet" type="text/css" />
</head>

<body bgproperties=fixed leftmargin=0 topmargin=0>
<?php _LayoutTopLeft(true); ?>

<div>
<h1>东方财富人民币美元中间价汇率实时数据接口的字段意义</h1>
<p>2016年6月15日
<br />因为<a href="20151225cn.php">新浪接口</a>提供的是实时交易数据, 而<a href="../../res/lofcn.php">LOF</a>普遍使用的美元人民币中间价,
在<a href="20150818cn.php">华宝油气</a>净值计算中跟最终官方数据相比有时候会出现0.1分的误差. 考虑到误差不大, 我也不会去做0.1分钱的套利, 而且我还相信交易值总会往中间价靠拢, 所以我一直没有去改它.
<br />今年以来国泰商品的基金经理费心费力, 在国内监管部门要求多个不同美股ETF持仓的条件下, 居然一直维持了<a href="../../res/sz160216cn.php">国泰商品净值</a>和USO几乎完全相同的变动,
由此在白天引发了大量跟原油期货CL的套利交易. 在我QQ群204836363中的高手zzzzv已经做到了0.05分的套利, 这样就必须使用中间价了. zzzzv根据长期经验给我确认了交易值不会往中间价靠拢,
并且给我提供了他手头的Excel+VBA工具中使用的<a href="http://quote.eastmoney.com/forex/USDCNY.html" target=_blank>东方财富人民币美元</a>的<a href="http://hq2gjqh.eastmoney.com/EM_Futures2010NumericApplication/Index.aspx?type=z&ids=usdcny0" target=_blank>中间价接口</a>.
<br />先写这个格式文档, 然后再改我的<font color=olive>ForexReference</font>类.
记录在<b><a href="/debug/eastmoney/usdcny.txt" target=_blank>usdcny.txt</a></b>中的数据如下:
<br /><font color=grey>var js={futures:["USDCNY0,USDCNY,美元人民币,6.5842,6.5835,6.5966,6.5966,6.5804,0,1,
0.0000,0,0,6.5842,0.0000,0,0,0.0124,0.19%,0.0000,
0,0,0,0,0,0.0024,0.0000,2016-06-14 23:45:00,3"]}</font>
<br />去掉前后双引号后, 按逗号','分隔的各个字段意义如下表.
<TABLE borderColor=#cccccc cellSpacing=0 width=640 border=1 class="text" id="cnstock">
       <tr>
        <td class=c1 width=40 align=center>序号</td>
        <td class=c1 width=300 align=center>原始数据内容</td>
        <td class=c1 width=300 align=center>字段意义</td>
      </tr>
      <tr>
        <td class=c1 align="center">0</td>
        <td class=c1 align="center">USDCNY0</td>
        <td class=c1 align="center">接口符号</td>
      </tr>
      <tr>
        <td class=c1 align="center">1</td>
        <td class=c1 align="center">USDCNY</td>
        <td class=c1 align="center">英文名字</td>
      </tr>
      <tr>
        <td class=c1 align="center">2</td>
        <td class=c1 align="center">美元人民币</td>
        <td class=c1 align="center">中文名字</td>
      </tr>
      <tr>
        <td class=c1 align="center">3</td>
        <td class=c1 align="center">6.5842</td>
        <td class=c1 align="center">昨日收盘价?</td>
      </tr>
      <tr>
        <td class=c1 align="center">4</td>
        <td class=c1 align="center">6.5835</td>
        <td class=c1 align="center">今日开盘价</td>
      </tr>
      <tr>
        <td class=c1 align="center">5</td>
        <td class=c1 align="center">6.5966</td>
        <td class=c1 align="center">当前价</td>
      </tr>
      <tr>
        <td class=c1 align="center">6</td>
        <td class=c1 align="center">6.5966</td>
        <td class=c1 align="center">今日最高价</td>
      </tr>
      <tr>
        <td class=c1 align="center">7</td>
        <td class=c1 align="center">6.5804</td>
        <td class=c1 align="center">今日最低价</td>
      </tr>
      <tr>
        <td class=c1 align="center">8-12</td>
        <td class=c1 align="center">0,1,0.0000,0,0</td>
        <td class=c1 align="center">(未知)</td>
      </tr>
      <tr>
        <td class=c1 align="center">13</td>
        <td class=c1 align="center">6.5842</td>
        <td class=c1 align="center">昨日结算价?</td>
      </tr>
      <tr>
        <td class=c1 align="center">14-16</td>
        <td class=c1 align="center">0.0000,0,0</td>
        <td class=c1 align="center">(未知)</td>
      </tr>
      <tr>
        <td class=c1 align="center">17</td>
        <td class=c1 align="center">0.0124</td>
        <td class=c1 align="center">涨跌</td>
      </tr>
      <tr>
        <td class=c1 align="center">18</td>
        <td class=c1 align="center">0.19%</td>
        <td class=c1 align="center">幅度</td>
      </tr>
      <tr>
        <td class=c1 align="center">19-26</td>
        <td class=c1 align="center">0.0000,0,0,0,0,0,0.0024,0.0000</td>
        <td class=c1 align="center">(未知)</td>
      </tr>
      <tr>
        <td class=c1 align="center">27</td>
        <td class=c1 align="center">2016-06-14 23:45:00</td>
        <td class=c1 align="center">日期和时间</td>
      </tr>
      <tr>
        <td class=c1 align="center">28</td>
        <td class=c1 align="center">3</td>
        <td class=c1 align="center">(未知)</td>
      </tr>
</TABLE>
</p>

<h3><a name="uscny">USCNY和USDCNY</a></h3>
<p>2016年6月16日
<br />昨晚自动校准用的东方财富的数据, 但是今天估算的<a href="../../res/sz162411cn.php">华宝油气净值</a>跟官方数据还是有偏差. 继续向zzzzv请教, 发现昨天用的东方财富USDCNY数据跟新浪USDCNY数据一样也是交易数据,
东方财富人民币美元中间价要用<a href="http://hq2gjqh.eastmoney.com/EM_Futures2010NumericApplication/Index.aspx?type=z&ids=uscny0" target=_blank>USCNY</a>.
</p>

</div>

<?php _LayoutBottom(true); ?>

</body>
</html>
