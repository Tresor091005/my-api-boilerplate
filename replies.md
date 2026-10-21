### Q01. Quel type d’entreprise vise le parcours vente et livraison ? 

en commencant cela parlant de l'erp en general, ce que je visais etait les petites et moyennes entreprise autour de moi parce que les grosses sont deja un peu trop ambitieux pour un debut, et parlant de celle là , de ma connaissance ce sont des gens qui ont plusieurs activites - pas tres grandes {il serait difficile de compter plus de 2 locations}, traitent des produits expirables, utilisent de petits carnets pour suivre les credits, souvent confie ces multiples activites qu'ils ont a d'autres perosnnes, des equipes disons de tailles 10. je paye tres souvent a manger en ligne donc je me fais livrer entre celle qui prend ma commande, cuisine et les livreurs . je ne sais pas si je peux attaquer les distribiteurs pour commencer vu que le flow necessiterait beaucoup de choses a preparer. 

donc quand j'ai pense a commande et livraison ce sont aux commerces qui se developpent a travers whatsapp surtout qui prennent des commandes et livrent qui m'interessent certains necessitant un paiement avant ou apres

### Q02. Comment veux-tu proposer les modules hors de cet ERP ? 
c'est un truc que j'ai mis en seconde priorite car me posait un peu trop de probleme par rapport a mon propre developpement. l'arch acteulle par exemple a une dependance assume sur currentOrganizationId qui n'aurait peut etre pas du etre si je veux vraiment une reutilisation extieur, ce qui fait que actuellement l'ensemble de l'app est construit comme single unit mais avec des frontieres assez claire pour permettre une portabilite ailleurs et tres peu de changement. Modulardependency test je pense m'aide beaucoup quand meme a savoir qui vient avant qui et ne pas faire de toile d'araignee. j'ai assume le cross des models pour ne pas me prendre la tete avec les relations eloquent {garder expressivete simple} et certains petits autres trucs. 

### Q03. Pourquoi un monolithe modulaire avec des packages Composer locaux ?

appris et acquis en entreprise, on commence toujours par le bas et on s'adapte en evoluant, sachant ou je vais je savais que j'aurai des milliers de routes a un moment et designer pleins de flow complementaires. on m'a initie au DDD et apres avoir lu quelques articles je me suis rendu compte que c'est ce genre de chose qu'il me fallait sans pour appliquer a la lettre. il faut savoir prendre ce qui t'es necessaire sans te casser la tete. le microservices si c'est a ca vous pensez ne me servira a rien, deja je suis seul sur mon projet en plus pour le public que je vise je ne me vois pas administrer une si grande architecture qui justifie, on m'a meme dit de mettre tout sur la db meme les queue et le cache mais un peu par choix totalement subjectif je prefere cette separation maintenant. il faut trouver la balance au fait. et de tres grandes entreprises comme shopify si je ne dis pas de betises {dans le meme domaine que je fais} a bati sur ce principe donc ca ne scale pas est faux. il regle pas mal de probleme tres rapidement, garde le travail focus sur un element et boost de productivite est valide. 

### Q04. Pourquoi les mutations renvoient-elles `204` par défaut ?

ah ha ! le frontend n'utilise pas toujours les reponses et ce souvent pour ses propres optimisations de requetes, tu as cree un element donc tu as deja la donne, recevoir 200 te suffit, tu as update deux champs, tu as fait une confirmation que tu attends juste pour switcher de page, en plus en faisant ca tu economises de la bande passante et le temps de construction des reponses qui parfois en laravel peut etre aussi long que le metier. cas de product>variant>options+stock....

### Q05. Quel problème veux-tu résoudre avec les shapes et includes ?
include rejoint le 04, optimser l'utilisation des resources alors que le shape rejoint un peu un truc qui vient tres souvent. i can create order but i cannot access the list of product. if manager gives me directly this permissions i can see a lot of things, which is not good for him. 
donc le but de shape est de pouvoir definir les limites de renvoie en le combinant aux autres permissions non direct pour permettre a un agent de faire son boulot sans avoir acces a la gestion des produits et meme temps absorber cette charge en backend sans demander a l'admin de se dire order.create n'est pas suffisant pour creer une vente, une analyse recente que j'ai fait chez cloudfare montre d'ailleur que chez eux on voulant creer des access tokens. ils ont edit et read. et edit contient en son sein read directement pas besoin de donner les deux ce qui fait sens.

### Q06. Pourquoi rendre le tenant explicite dans les requêtes ?
rien n'est plus sur et direct que l'explicite. de toute facon les scope global en plus d'avoir toujours des edge cases, rendent le debogage complique et l'ecrire de code laxiste sur un truc qui est quand meme assez important qu'est le tenancy. je me donne cette rigueur et discipline la, c'est comme le ; pour moi ayant d'abors appris python, tellement je haissais au debut mais maintenant ca va et d'ailleurs le code est ecrit et review par ia, c'est maintenant plus que jamais que de tels choses qualitatives qui etaient difficile au debut sont no op actu. 
aussi dans la vie d'une app il n'y a pas que http frontface, tu as le scheduler, queues, console commands et dans ma volonte de reutiliser le meme service, je prefere ce controle fin.

The Zen of Python, by Tim Peters

Beautiful is better than ugly.
Explicit is better than implicit.
Simple is better than complex.
Complex is better than complicated.
Flat is better than nested.
Sparse is better than dense.
Readability counts.
Special cases aren't special enough to break the rules.
Although practicality beats purity.
Errors should never pass silently.
Unless explicitly silenced.
In the face of ambiguity, refuse the temptation to guess.
There should be one-- and preferably only one --obvious way to do it.
Although that way may not be obvious at first unless you're Dutch.
Now is better than never.
Although never is often better than *right* now.
If the implementation is hard to explain, it's a bad idea.
If the implementation is easy to explain, it may be a good idea.
Namespaces are one honking great idea -- let's do more of those!