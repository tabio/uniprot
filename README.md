uniprot
======================
uniprotのWebサイトから情報を取得し加工するためのプログラム置き場です。  
uniprotについては[こちら](http://www.uniprot.org/)からアクセスできます。
 
### 各プログラムの概要 ###

### imp_phos_snp.php
- 対象のタンパクのリン酸化・SNP情報の取得プログラム
  + NCBIのAccession NumberをuniprotのAccession Numberに変換
  + 対象のタンパクに対するリン酸化の情報、SNPの情報を取得

> php imp_phos_snp.php args1 (args2, ...)

    [uniprot accession number]  
      |__ [phosphorylation] ____ [position]  
      |                           |__ [reference]  
      |                           |__ [status]  
      |__ [snp] ________________ [position]  
      |                           |__ [reference]  
      |                           |__ [original]  
      |                           |__ [variant]  
      |                           |__ [status]  
      |__ [sequence]
