<?xml version="1.0" encoding="UTF-8"?>
<xsl:transform xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
  xmlns="http://www.tei-c.org/ns/1.0" 
  xmlns:html="http://www.w3.org/1999/xhtml"
  exclude-result-prefixes="html"
  extension-element-prefixes=""
  >
  <xsl:output indent="yes" encoding="UTF-8" method="xml" omit-xml-declaration="yes"/>
  <xsl:variable name="lf" select="'&#10;'"/>
  <xsl:variable name="ABC">ABCDEFGHIJKLMNOPQRSTUVWXYZÀÂÄÆÇÉÈÊËÎÏÑÔÖŒÙÛÜ</xsl:variable>
  <xsl:variable name="abc">abcdefghijklmnopqrstuvwxyzàâäæçéèêëîïñôöœùûü</xsl:variable>

  <xsl:template match="html:*">
    <xsl:element name="{local-name()}">
      <xsl:apply-templates select="node() | @*"/>
    </xsl:element>
  </xsl:template>
  <xsl:template match="node()">
    <xsl:copy>
      <xsl:apply-templates select="node() | @*"/>
    </xsl:copy>
  </xsl:template>
  <xsl:template match="html:link | html:script | html:style"/>
  <xsl:template match="html:meta[@http-equiv]"/>
<!--
STRUCTURE
-->
  <xsl:template match="html:html">
    <TEI>
      <xsl:apply-templates select="node() | @*"/>
    </TEI>
  </xsl:template>
  <xsl:template match="html:head">
    <teiHeader>
      <xsl:apply-templates select="node() | @*"/>
    </teiHeader>
  </xsl:template>
  <xsl:template match="html:body">
    <text>
      <xsl:apply-templates select="@*"/>
      <body>
        <div>
          <xsl:apply-templates/>
        </div>
      </body>
    </text>
  </xsl:template>
  <xsl:template match="html:section | html:article">
    <div type="{local-name()}">
      <xsl:apply-templates select="@*"/>
    </div>
  </xsl:template>
  <xsl:template match="html:img">
    <graphic>
      <xsl:copy-of select="@*"/>
    </graphic>
  </xsl:template>
  <!-- no hierachical grouping ? -->
  <xsl:template match="html:div">
    <xsl:variable name="mixed">
      <xsl:for-each select="text()">
        <xsl:value-of select="normalize-space(.)"/>
      </xsl:for-each>
    </xsl:variable>   
    <xsl:choose>
      <xsl:when test="$mixed = '' and count(*) = 1 and *[@class='pagenum']">
        <xsl:apply-templates/>
      </xsl:when>
      <!-- div to cut -->
      <xsl:when test="contains(' siteNotice jump-to-nav footer ', concat(' ', @id, ' '))"/>
      <!-- div to cross -->
      <xsl:when test="contains(' mw-content-text ', concat(' ', @id, ' '))">
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="contains(@class, 'stanza')">
        <lg>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <xsl:apply-templates/>
        </lg>
      </xsl:when>
      <xsl:when test=" (@class='header' or @class='heading') and (html:h1|html:h2|html:h3|html:h4)">
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="contains(@class, 'poetry')">
        <div type="poem">
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <xsl:apply-templates/>
        </div>
      </xsl:when>
      <xsl:when test="contains(@class, 'poem')">
        <quote>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <xsl:apply-templates/>
        </quote>
      </xsl:when>
      <xsl:when test="@class = 'quote'">
        <quote>
          <xsl:apply-templates select="@*"/>
          <xsl:apply-templates/>
        </quote>
      </xsl:when>
      <!-- specific Gutenberg for Tocs -->
      <xsl:when test="@class = 'index'"/>
      <xsl:otherwise>
        <div>
          <xsl:apply-templates select="@*"/>
          <xsl:apply-templates/>
        </div>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- Wikisource -->
  <xsl:template match="html:ol[@class='references']">
    <div type="notes">
      <xsl:apply-templates/>
    </div>
  </xsl:template>
  <xsl:template match="html:ol[@class='references']/html:li">
    <note>
      <xsl:attribute name="xml:id">
        <xsl:choose>
          <xsl:when test="starts-with(@id, 'cite_note-')">
            <xsl:text>fn</xsl:text>
            <xsl:value-of select="substring-after(@id, 'cite_note-')"/>
          </xsl:when>
          <xsl:otherwise>
            <xsl:value-of select="@id"/>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <xsl:apply-templates/>
    </note>
  </xsl:template>
<!--
BLOCKS
-->
  <!-- Hierarchical headers is not sure -->
  <xsl:template match="html:h1 | html:h2 | html:h3 | html:h4 | html:h5 | html:h6">
    <!--
    <xsl:text disable-output-escaping="yes">
&lt;/div>
&lt;div>
</xsl:text>
-->
    <head>
      <xsl:apply-templates select="@*"/>
      <xsl:attribute name="type">
        <xsl:value-of select="local-name()"/>
      </xsl:attribute>
      <xsl:apply-templates/>
    </head>
  </xsl:template>
  <!--
  <xsl:template match="html:h1//text() | html:h2//text() | html:h3//text() | html:h4//text() | html:h5//text() | html:h6//text()">
    <xsl:value-of select="translate(., $ABC, $abc)"/>
  </xsl:template>
  -->
  <xsl:template match="html:ul | html:ol">
    <list>
      <xsl:apply-templates select="@*"/>
      <xsl:attribute name="type">
        <xsl:value-of select="local-name()"/>
      </xsl:attribute>
      <xsl:apply-templates/>
    </list>
  </xsl:template>
  <xsl:template match="html:li">
    <item>
      <xsl:apply-templates select="@*"/>
      <xsl:apply-templates/>
    </item>
  </xsl:template>
  <xsl:template match="html:blockquote">
    <quote>
      <xsl:apply-templates select="node() | @*"/>
    </quote>
  </xsl:template>
  <xsl:template match="html:p">
    <xsl:variable name="mixed">
      <xsl:for-each select="text()">
        <xsl:value-of select="normalize-space(.)"/>
      </xsl:for-each>
    </xsl:variable>   
    <xsl:if test="contains(@class, 'p2')">
      <p/>
    </xsl:if>
    <xsl:choose>
      <xsl:when test="$mixed = '' and count(*) = 1 and *[@class='pagenum']">
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="ancestor::html:div[@class='poetry' or @class='stanza' or @class='poem'] ">
        <l>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <xsl:apply-templates/>
        </l>
      </xsl:when>
      <xsl:when test="@class ='annee' or @class ='date' ">
        <dateline>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <xsl:apply-templates/>
        </dateline>
      </xsl:when>
      <xsl:when test=" @class = 'titre' ">
        <label>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <xsl:apply-templates/>
        </label>
      </xsl:when>
      <xsl:when test=" @class = 'citation' ">
        <quote>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <p>
            <xsl:apply-templates/>
          </p>
        </quote>
      </xsl:when>
      <xsl:when test=" @class = 'resume' ">
        <argument>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <p>
            <xsl:apply-templates/>
          </p>
        </argument>
      </xsl:when>
      <xsl:when test="contains(@class, 'poem')">
        <lg>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <xsl:apply-templates/>
        </lg>
      </xsl:when>
      <xsl:when test="@class ='vers' ">
        <l>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <xsl:apply-templates/>
        </l>
      </xsl:when>
      <xsl:when test="contains(@class, 'note')">
        <note>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id | @n"/>
          <xsl:apply-templates/>
        </note>
      </xsl:when>
      <xsl:when test="contains(@class, 'acteur')">
        <speaker>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id | @n"/>
          <xsl:apply-templates/>
        </speaker>
      </xsl:when>
      <xsl:when test="contains(@class, 'auteur')">
        <bibl>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id | @n"/>
          <xsl:apply-templates/>
        </bibl>
      </xsl:when>
      <xsl:when test="contains(@class, 'quote')">
        <quote>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id | @n"/>
          <xsl:apply-templates/>
        </quote>
      </xsl:when>
      <xsl:otherwise>
        <p>
          <xsl:apply-templates select="@xml:id | @xml:lang | @lang | @id "/>
          <xsl:choose>
            <xsl:when test="@class = 'p2'"/>
            <xsl:when test="not(@class) or @class=''"/>
            <xsl:otherwise>
              <xsl:attribute name="rend">
                <xsl:value-of select="@class"/>
              </xsl:attribute>
            </xsl:otherwise>
          </xsl:choose>
          <xsl:apply-templates/>
        </p>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
<!-- 
PHRASES
  -->
  <!-- typographic phrasing -->
  <xsl:template match="html:b | html:i | html:small | html:sub | html:u">
    <hi>
      <xsl:apply-templates select="@*"/>
      <xsl:attribute name="rend">
        <xsl:value-of select="local-name(.)"/>
        <xsl:if test="@class != ''">
          <xsl:text> </xsl:text>
          <xsl:value-of select="normalize-space(@class)"/>
        </xsl:if>
      </xsl:attribute>
      <xsl:apply-templates/>
    </hi>
  </xsl:template>
  <!-- -->
  <xsl:template match="html:sup">
    <xsl:choose>
      <xsl:when test="@class='reference'">
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:otherwise>
        <hi rend="sup">
          <xsl:apply-templates/>
        </hi>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- specific to Gutenberg ? -->
  <xsl:template match="html:cite">
    <title>
      <xsl:apply-templates select="@*"/>
      <xsl:apply-templates/>
    </title>
  </xsl:template>
  <xsl:template match="html:br">
    <xsl:value-of select="$lf"/>
    <lb/>
  </xsl:template>
  <xsl:template match="html:span">
    <xsl:choose>
      <!-- Wikisource cut -->
      <xsl:when test="contains(@class, 'mw-cite-backlink')"/>
      <!-- Wikisource cross -->
      <xsl:when test="contains(@class, 'mw-headline')">
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="contains(@class, 'reference-text')">
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="contains(@class, 'pagenum')">
        <pb n="{@id}"/>
      </xsl:when>
      <!-- span class="i3 smcap" -->
      <xsl:when test="contains(@class, 'smcap') and ancestor::*[@class='quote']">
        <author>
          <xsl:apply-templates/>
        </author>
      </xsl:when>
      <xsl:when test=". = ''">
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="@lang | @xml:lang">
        <foreign>
          <xsl:apply-templates select="@*"/>
          <xsl:apply-templates/>
        </foreign>
      </xsl:when>
      <!-- Roman number ? -->
      <xsl:when test="@class='romain'">
        <num>
          <xsl:apply-templates/>
        </num>
      </xsl:when>
      <xsl:when test="@class='smaller'">
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="@class='add2em'">
        <seg type="tab">    </seg>
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="@class='add4em'">
        <seg type="tab">    </seg>
        <seg type="tab">    </seg>
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="@class='add6em'">
        <seg type="tab">    </seg>
        <seg type="tab">    </seg>
        <seg type="tab">    </seg>
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:when test="@class='add8em'">
        <seg type="tab">    </seg>
        <seg type="tab">    </seg>
        <seg type="tab">    </seg>
        <seg type="tab">    </seg>
        <xsl:apply-templates/>
      </xsl:when>
      <xsl:otherwise>
        <hi>
          <xsl:apply-templates select="@*"/>
          <xsl:attribute name="rend">
            <xsl:choose>
              <xsl:when test="@class = 'smcap'">sc</xsl:when>
              <xsl:when test="@class">
                <xsl:value-of select="@class"/>
              </xsl:when>
              <xsl:when test="contains(@style, 'small-caps')">sc</xsl:when>
            </xsl:choose>
          </xsl:attribute>
          <xsl:apply-templates/>
        </hi>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <xsl:template match="html:hr"/>
  <xsl:template match="html:em">
    <emph>
      <xsl:apply-templates select="@* | node()"/>
    </emph>
  </xsl:template>
  <!-- Links -->
  <xsl:template match="html:a">
    <xsl:choose>
      <!-- Useful anchor ? -->
      <xsl:when test=". = ''"/>
      <!-- 
<a href="#footnote11" title="Go to footnote 11"><span class="smaller">[11]</span></a>
      -->
      <xsl:otherwise>
        <ref>
          <xsl:apply-templates select="@*"/>
          <xsl:attribute name="target">
            <xsl:choose>
              <xsl:when test="starts-with(@href, '#cite_note-')">
                <xsl:text>#fn</xsl:text>
                <xsl:value-of select="substring-after(@href, '#cite_note-')"/>
              </xsl:when>
              <xsl:otherwise>
                <xsl:value-of select="@href"/>
              </xsl:otherwise>
            </xsl:choose>
          </xsl:attribute>
          <xsl:apply-templates/>
        </ref>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <xsl:template match="html:table">
    <table>
      <xsl:apply-templates select="node()|@*"/>
    </table>
  </xsl:template>
  <xsl:template match="html:tr">
    <row>
      <xsl:apply-templates select="node()|@*"/>
    </row>
  </xsl:template>
  <xsl:template match="html:td">
    <cell>
      <xsl:apply-templates select="node()|@*"/>
    </cell>
  </xsl:template>
  <xsl:template match="html:th">
    <cell role="label">
      <xsl:apply-templates select="node()|@*"/>
    </cell>
  </xsl:template>
  <!-- 
 ATTRIBUTES
  -->
  <xsl:template match="@*"/>
  <xsl:template match="@n | @rend | @xml:id | @xml:lang">
    <xsl:copy>
      <xsl:value-of select="."/>
    </xsl:copy>
  </xsl:template>
  <xsl:template match="@lang">
    <xsl:attribute name="xml:lang">
      <xsl:value-of select="."/>
    </xsl:attribute>
  </xsl:template>
  <xsl:template match="@id | @name">
    <xsl:attribute name="xml:id">
      <xsl:value-of select="."/>
    </xsl:attribute>
  </xsl:template>
  <xsl:template match="@class">
    <xsl:attribute name="rend">
      <xsl:value-of select="."/>
    </xsl:attribute>
  </xsl:template>
  <!-- Gutenberg notices -->
  <xsl:template match="html:pre">
    <xsl:choose>
      <xsl:when test="contains(., 'Gutenberg')"/>
      <xsl:otherwise>
        <xsl:copy>
          <xsl:apply-templates select="node() | @*"/>
        </xsl:copy>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- Guntenberg page number  -->
  <xsl:template match="html:span[@class='pagenum']">
    <pb n="{normalize-space(translate(., '()[]p.', ''))}"/>
  </xsl:template>
    <!-- A counting template to produce inlines -->
  <xsl:template name="divClose">
    <xsl:param name="n"/>
    <xsl:choose>
      <xsl:when test="$n &gt; 0">
        <xsl:processing-instruction name="div">/</xsl:processing-instruction>
        <xsl:call-template name="divClose">
          <xsl:with-param name="n" select="$n - 1"/>
        </xsl:call-template>
      </xsl:when>     
    </xsl:choose>
  </xsl:template>
  <xsl:template name="divOpen">
    <xsl:param name="n"/>
    <xsl:choose>
      <xsl:when test="$n &gt; 0">
        <xsl:processing-instruction name="div"/>
        <xsl:call-template name="divOpen">
          <xsl:with-param name="n" select="$n - 1"/>
        </xsl:call-template>
      </xsl:when>
    </xsl:choose>
  </xsl:template>
</xsl:transform>