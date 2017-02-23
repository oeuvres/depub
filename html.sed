#!/bin/sed -E -f
# Chained substitutions to clean some html oddities
# should work as a sed script in unicode (on one line, no dotall option)
# maybe read as a regexp program by PHP or Java
# default MacOSX sed is not perl compatible, there is no support for backreference in search “\1”, and assertions (?…)
# frederic.glorieux@fictif.org

# delete all ids ?
s@ name="[^"]*"@@g
# s@&([A-Za-z]*[^;])@&amp;$1@g # incorrect entities
s@&nbsp;@ @g
