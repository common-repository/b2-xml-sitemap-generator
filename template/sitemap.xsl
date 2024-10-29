<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" 
                xmlns:html="http://www.w3.org/TR/REC-html40"
                xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
	<xsl:template match="/">
		<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<title>XML Sitemap</title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<style type="text/css">
					body {
						font-family: Verdana;
						font-size:13px;
					}
					
					#intro {
						background-color:#f7f7f7;
						border:1px #cccccc solid;
						padding:5px 13px 5px 13px;
						margin:10px;
					}
					
					#intro p {
						line-height:	16.8667px;
					}
					
					td {
						font-size:11px;
					}
					
					th {
						text-align:left;
						padding-right:30px;
						font-size:11px;
					}
					
					tr.high {
						background-color:#f7f7f7;
					}
					
					#footer {
						padding:2px;
						margin:10px;
						font-size:8pt;
						color:#999999;
					}
					
					#footer a {
						color:#999999;
					}
					
					a {
						color:#333333;
					}
				</style>
			</head>
			<body>
				<h1>XML Sitemap</h1>
				<div id="intro">
					<p>
						This XML sitemap has been created by <a href="http://www.b2foundry.com" target="_blank">B2Foundry</a>
					</p>
				</div>
				<div id="content">
					<table cellpadding="5">
						<tr style="border-bottom:1px black solid;">
							<th>URL</th>
							<th>Priority</th>
							<th>Change Frequency</th>
							<th>LastChange (GMT)</th>
						</tr>
						<xsl:variable name="lower" select="'abcdefghijklmnopqrstuvwxyz'"/>
						<xsl:variable name="upper" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'"/>
						<xsl:for-each select="sitemap:urlset/sitemap:url">
							<tr>
								<xsl:if test="position() mod 2 != 1">
									<xsl:attribute  name="class">high</xsl:attribute>
								</xsl:if>
								<td>
									<xsl:variable name="itemURL">
										<xsl:value-of select="sitemap:loc"/>
									</xsl:variable>
									<a href="{$itemURL}">
										<xsl:value-of select="sitemap:loc"/>
									</a>
								</td>
								<td>
									<xsl:value-of select="concat(sitemap:priority*100,'%')"/>
								</td>
								<td>
									<xsl:value-of select="concat(translate(substring(sitemap:cfreq, 1, 1),concat($lower, $upper),concat($upper, $lower)),substring(sitemap:cfreq, 2))"/>
								</td>
								<td>
									<xsl:value-of select="concat(substring(sitemap:lastmod,0,11),concat(' ', substring(sitemap:lastmod,12,5)))"/>
								</td>
							</tr>
						</xsl:for-each>
					</table>
				</div>
				<div id="footer">
					Sitmap Generated with <a href="http://b2foundry.com/products" title="B2XML Sitemap Plugin for Wordpress">B2XML Sitemap Plugin for Wordpress</a> by <a href="http://www.b2foundry.com/">B2Foundry</a>.
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>