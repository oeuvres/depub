<?xml version='1.0' encoding='UTF-8'?>
<xsl:stylesheet 
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:bml="http://efele.net/2010/ns/bml"
  xmlns="http://www.tei-c.org/ns/1.0" 
  version="1.1">
  <xsl:output encoding="UTF-8" method="xml" media-type="text/html" />
  <xsl:variable name="caps">ABCDEFGHIJKLMNOPQRSTUVWXYZÆŒÇÀÁÂÃÄÅÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝ</xsl:variable>
  <xsl:variable name="mins">abcdefghijklmnopqrstuvwxyzæœçàáâãäåèéêëìíîïòóôõöùúûüý</xsl:variable>
  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>
  <xsl:template match="bml:bml">
    <TEI>
      <teiHeader>
        <!-- TODO -->
      </teiHeader>
      <text>
        <xsl:apply-templates select="bml:page-sequences"/>
      </text>
    </TEI>
  </xsl:template>
  <xsl:template match="bml:page-sequences">
    <body>
      <xsl:apply-templates select="node()|@*"/>
    </body>
  </xsl:template>
  <xsl:template match="bml:page-sequence">
    <div>
      <xsl:apply-templates select="node()|@*"/>
    </div>
  </xsl:template>
  <xsl:template match="bml:p">
    <p>
      <xsl:apply-templates select="node()|@*"/>
    </p>
  </xsl:template>
  <xsl:template match="bml:p/@class">
    <xsl:attribute name="rend">
      <xsl:choose>
        <xsl:when test=". = 'c'">center</xsl:when>
        <xsl:otherwise>
          <xsl:value-of select="."/>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:attribute>
  </xsl:template>
  <xsl:template match="bml:i">
    <hi rend="i">
      <xsl:apply-templates select="node()|@*"/>
    </hi>
  </xsl:template>
  <xsl:template match="bml:sup">
    <hi rend="sup">
      <xsl:apply-templates select="node()|@*"/>
    </hi>
  </xsl:template>
  <xsl:template match="bml:s">
    <hi rend="sc">
      <xsl:apply-templates select="node()|@*"/>
    </hi>
  </xsl:template>
  <xsl:template match="bml:s/text()">
    <xsl:value-of select="translate(., $caps, $mins)"/>
  </xsl:template>
  <xsl:template match="bml:signature">
    <signed>
      <xsl:apply-templates select="node()|@*"/>
    </signed>
  </xsl:template>
  <xsl:template match="bml:salutation">
    <salute>
      <xsl:apply-templates select="node()|@*"/>
    </salute>
  </xsl:template>
  <xsl:template match="bml:date">
    <dateline>
      <xsl:apply-templates select="node()|@*"/>
    </dateline>
  </xsl:template>
  <xsl:template match="bml:letter">
    <figure>
      <xsl:apply-templates select="@*"/>
      <xsl:attribute name="type">letter</xsl:attribute>
      <xsl:apply-templates/>
    </figure>
  </xsl:template>
  <xsl:template match="bml:letter/bml:h3">
    <head>
      <xsl:apply-templates select="node()|@*"/>
    </head>
  </xsl:template>
  <xsl:template match="bml:blockquote">
    <quote>
      <xsl:apply-templates select="@*"/>
      <xsl:choose>
        <xsl:when test="bml:poem">
          <xsl:attribute name="type">poem</xsl:attribute>
        </xsl:when>     
      </xsl:choose>
      <xsl:apply-templates/>
    </quote>
  </xsl:template>
  <xsl:template match="bml:poem">
    <xsl:apply-templates/>
  </xsl:template>
  <xsl:template match="@class">
    <xsl:variable name="rend">
      <xsl:choose>
        <xsl:when test=". = 'smaller'"/>
        <xsl:otherwise>
          <xsl:value-of select="."/>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
    <xsl:if test="$rend != ''">
      <xsl:attribute name="rend">
        <xsl:value-of select="$rend"/>
      </xsl:attribute>
    </xsl:if>
  </xsl:template>
  <xsl:template match="bml:blockquote/bml:h3 | bml:poem/bml:h3">
    <label>
      <xsl:apply-templates select="node()|@*"/>
    </label>
  </xsl:template>
  <xsl:template match="bml:l">
    <l>
      <xsl:apply-templates select="@*"/>
      <xsl:choose>
        <xsl:when test="@indent = 1">
          <space rend="tab">
            <xsl:text>    </xsl:text>
          </space>
        </xsl:when>
        <xsl:when test="@indent = 2">
          <space rend="tab">
            <xsl:text>    </xsl:text>
          </space>
          <space rend="tab">
            <xsl:text>    </xsl:text>
          </space>
        </xsl:when>
        <xsl:when test="@indent = 3">
          <space rend="tab">
            <xsl:text>    </xsl:text>
          </space>
          <space rend="tab">
            <xsl:text>    </xsl:text>
          </space>
          <space rend="tab">
            <xsl:text>    </xsl:text>
          </space>
        </xsl:when>
        <xsl:when test="@indent">
          <space extent="{@indent} tabs">
            <xsl:value-of select="substring('                                                          ', 1, 4 * @indent)"/>
          </space>
        </xsl:when>
      </xsl:choose>
      <xsl:apply-templates/>
    </l>
  </xsl:template>
  <xsl:template match="bml:img">
    <figure>
      <graphic url="{@src}">
        <desc>
          <xsl:value-of select="@alt"/>
        </desc>
      </graphic>
    </figure>
  </xsl:template>
  <xsl:template match="bml:l/@indent"/>
  <xsl:template match="bml:lg">
    <lg>
      <xsl:apply-templates select="node()|@*"/>
    </lg>
  </xsl:template>
  <xsl:template match="bml:pagenum">
    <pb n="{@num}"/>
  </xsl:template>
  <xsl:template match="bml:correction">
    <corr>
      <xsl:apply-templates select="node()|@*"/>
    </corr>
  </xsl:template>
  <xsl:template match="bml:correction/@original">
    <xsl:attribute name="n">
      <xsl:apply-templates/>
    </xsl:attribute>
  </xsl:template>
  <xsl:template match="bml:table">
    <table>
      <xsl:apply-templates select="node()|@*"/>
    </table>
  </xsl:template>
  <xsl:template match="bml:tbody">
    <xsl:apply-templates/>
  </xsl:template>
  <xsl:template match="bml:tr">
    <row>
      <xsl:apply-templates select="node()|@*"/>
    </row>
  </xsl:template>
  
  <xsl:template match="bml:td">
    <cell>
      <xsl:apply-templates select="@*"/>
      <xsl:variable name="rend" select="normalize-space(concat(@text-align, ' ', @vertical-align))"/>
      <xsl:if test="$rend != ''">
        <xsl:attribute name="rend">
          <xsl:value-of select="$rend"/>
        </xsl:attribute>
      </xsl:if>
      <xsl:apply-templates/>
    </cell>
  </xsl:template>
  <xsl:template match="@text-align | @vertical-align"/>
  <xsl:template match="@colspan">
    <xsl:attribute name="cols">
      <xsl:apply-templates/>
    </xsl:attribute>
  </xsl:template>
  <xsl:template match="@rowspan">
    <xsl:attribute name="rows">
      <xsl:apply-templates/>
    </xsl:attribute>
  </xsl:template>
</xsl:stylesheet>