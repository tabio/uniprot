uniprot
======================
uniprotのWebサイトから情報を取得し加工するためのプログラム置き場です。  
uniprotについては[こちら](http://www.uniprot.org/)からアクセスできます。
 
### 各プログラムの概要 ###
+ 対象のタンパクのリン酸化・SNIP情報の取得
  - NCBIのAccession NumberをuniprotのAccession Numberに変換
  - 対象のタンパクに対するリン酸化の情報、SNIPの情報を取得

 
### その他 ###
> php aaa.php args1 (args2, ...)
>
> > [uniprot accession number]
> > |__ [phospho]  ___ [position]
> > |                      |__ [reference]
> > |                      |__ [reference]
> > |__ [snip]     ___ [position]
> > |                      |__ [reference]
> > |__ [sequence]
 
