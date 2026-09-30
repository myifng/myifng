<?xml version="1.0" encoding="UTF-8"?>
<!-- साइटमैप को ब्राउज़र में पढ़ने लायक बनाता है (सर्च इंजन पर कोई असर नहीं) -->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
  xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
<xsl:output method="html" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
<html lang="hi"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/><meta name="robots" content="noindex"/>
<title>XML साइटमैप</title>
<style>
body{font:15px/1.5 system-ui,"Noto Sans Devanagari",sans-serif;margin:0;background:#f5f6f8;color:#15161a}
header{background:#15161a;color:#fff;padding:18px 20px}h1{margin:0;font-size:20px}header p{margin:4px 0 0;opacity:.75;font-size:13px}
main{padding:16px 20px;max-width:1100px;margin:auto}table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e3e5ea}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #eef0f3;font-size:13px;vertical-align:top}th{background:#fafbfc}
td a{color:#b3121f;word-break:break-all}.n{color:#6b7280;white-space:nowrap}
</style></head><body>
<header><h1>XML साइटमैप</h1><p>यह पेज Google और दूसरे सर्च इंजन के लिए है।
<xsl:if test="s:sitemapindex">इसमें <xsl:value-of select="count(s:sitemapindex/s:sitemap)"/> साइटमैप हैं।</xsl:if>
<xsl:if test="s:urlset">इसमें <xsl:value-of select="count(s:urlset/s:url)"/> पते हैं।</xsl:if></p></header>
<main>
<xsl:if test="s:sitemapindex">
<table><tr><th>साइटमैप</th><th>आख़िरी बदलाव</th></tr>
<xsl:for-each select="s:sitemapindex/s:sitemap"><tr><td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td><td class="n"><xsl:value-of select="substring(s:lastmod,1,16)"/></td></tr></xsl:for-each>
</table></xsl:if>
<xsl:if test="s:urlset">
<table><tr><th>#</th><th>पता</th><th>शीर्षक / इमेज / वीडियो</th><th>तारीख़</th></tr>
<xsl:for-each select="s:urlset/s:url"><tr><td class="n"><xsl:value-of select="position()"/></td><td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
<td><xsl:value-of select="news:news/news:title"/><xsl:value-of select="video:video/video:title"/>
<xsl:if test="image:image"> <span class="n">(<xsl:value-of select="count(image:image)"/> इमेज)</span></xsl:if></td>
<td class="n"><xsl:value-of select="substring(concat(s:lastmod, news:news/news:publication_date, video:video/video:publication_date),1,16)"/></td></tr></xsl:for-each>
</table></xsl:if>
</main></body></html>
</xsl:template>
</xsl:stylesheet>
