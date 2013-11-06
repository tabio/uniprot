#!/usr/bin/env python
#coding:utf-8

from BeautifulSoup import BeautifulSoup
import urllib2

response = urllib2.urlopen('http://www.uniprot.org/uniprot/?query=NP_001092&sort=score')
html = response.read()
soup = BeautifulSoup(html)

try:
  for tmp in soup.findAll("table", id="results"):
    for tr in tmp("tr"):
      i = 0
      for td in tr("td"):
        i += 1
        if i == 2:
          print td.a.string
except:
  print "error"




