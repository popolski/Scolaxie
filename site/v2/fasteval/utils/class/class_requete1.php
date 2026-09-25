<?php



 class Requete1{



  private $session;
  private $dbh;
  private $debut_perio;
  private $fin_perio;
  private $debut_perio_fr; 
  private $fin_perio_fr;
  private $nom;
  private $eleve;
  private $matcat;
    
    public function __construct(array $session,$dbh){
        
        $this->session=$session;
        $this->dbh=$dbh;
        $this->info_session();
        $this->info_eleve();
    }

    public function periode($type){

    if($type=='debut'){return $this->debut_perio_fr;} 
    if($type=='fin'){return $this->fin_perio_fr;}
    }

    public function eleve($quoi){
      if($quoi=='prenom'){return $this->eleve[1];}
      if($quoi=='naissance'){return date("d/m/Y",strtotime($this->eleve[2]));}
      if($quoi=='nom'){return $this->nom;}

    }


    private function info_session(){

      $this->debut_perio=$this->session['date_debut'];
      $this->fin_perio=$this->session['date_fin'];
      $this->debut_perio_fr= date("d/m/Y",strtotime($this->debut_perio));
      $this->fin_perio_fr= date("d/m/Y",strtotime($this->fin_perio));

    }

    private function info_eleve(){
      $this->dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_WARNING);

    $idEleve=$this->session['eleve'];
    $req=$this->dbh->prepare('SELECT id_eleve,nom,prenom,date_naiss FROM classe WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
    $req->bindParam(':id_eleve',$idEleve);
    $req->bindParam(':id_enseignant',$_SESSION['id_enseignant']);
    $req->execute();
    $ligne=$req->fetch(PDO::FETCH_NUM);
     $req->closeCursor();

    $this->nom=strtoupper($ligne[1]);
    $this->eleve=array($ligne[0],$ligne[2],$ligne[3]);
    }

    


  

// Toutes les méthodes ci-dessous interrogent comp_type/comp_eleves pour le
// même élève et la même période, en ne changeant que la colonne sélectionnée
// et, parfois, un filtre matière/catégorie en plus : voir requeteAgregat().
private function requeteAgregat($select, array $filtres = array(), $orderBy = ''){

    $conditions = array(
        'comp_eleves.id_eleve=:eleveId',
        '(comp_eleves.date_acqui BETWEEN :debut AND :fin)',
        'id_enseignant=:id_enseignant',
    );
    $params = array(
        ':eleveId' => $this->eleve[0],
        ':debut' => $this->debut_perio,
        ':fin' => $this->fin_perio,
        ':id_enseignant' => $_SESSION['id_enseignant'],
    );

    foreach($filtres as $colonne=>$valeur){
        $conditions[] = "comp_type.$colonne=:$colonne";
        $params[":$colonne"] = $valeur;
    }

    $sql = "SELECT $select FROM comp_type INNER JOIN comp_eleves ON comp_type.id_comp=comp_eleves.id_comp WHERE ".implode(' AND ', $conditions).$orderBy;

    $req = $this->dbh->prepare($sql);
    foreach($params as $cle=>$valeur){ $req->bindValue($cle, $valeur); }
    $req->execute();
    $valeur = $req->fetchAll(PDO::FETCH_NUM);
    $req->closeCursor();
    return $valeur;
}

// LE BULLETIN SUIT L'ORDRE RANGE A LA MAIN, depuis le 06/09/2026.
//
// Il n'en suivait aucun : les competences sortaient par id_comp DECROISSANT -
// c'est-a-dire de la plus recemment creee a la plus ancienne, un ordre qui ne
// veut rien dire pour une lectrice - et les categories comme les matieres
// n'avaient AUCUN `ORDER BY` du tout, donc l'ordre arbitraire de MySQL.
// Demande de le responsable technique : « ajoute la possibilite pour l'enseignant d'ordonner les
// competences ».
//
// CETTE POSSIBILITE EXISTAIT DEJA, le bulletin l'ignorait. `comp_type.ordre` et
// `comp_type.ordre_categorie` sont remplis par le glisser-deposer de
// liste-competences.php, et l’enseignante s'en sert : 8 matieres sur 16 portaient un
// ordre non alphabetique le jour meme. Plutot que d'inventer un second
// rangement, propre au bulletin, qu'il aurait fallu tenir a jour deux fois, on
// lit celui qui existe. Ranger une fois dans la bibliotheque range partout.
//
// UNE LIGNE JAMAIS DEPLACEE VAUT 0 et retombe donc sur son libelle, exactement
// la convention de liste-competences.php : tant que personne n'a rien range, le
// bulletin est simplement alphabetique - jamais dans l'ordre de creation.
public function listeCommentaire($categorie){
    return $this->requeteAgregat(
        'comp_type.commentaire,comp_eleves.niveau,comp_eleves.nb',
        array('categorie'=>$categorie),
        ' ORDER BY comp_type.ordre ASC, comp_type.commentaire ASC'
    );
}

 public function listeCategorie($matiere){
    // Le troisieme parametre porte tout ce qui suit le WHERE, GROUP BY compris.
    // Il en faut un ici : `ordre_categorie` est stocke sur chaque competence, et
    // un simple DISTINCT rendrait deux fois la meme categorie si ses lignes ne
    // portaient pas toutes le meme rang. MIN() tranche - meme precaution que
    // dans liste-competences.php.
    // Le PDF ne lit que la premiere colonne ($categoryRow[0]) : la seconde ne
    // sert qu'au tri.
    return $this->requeteAgregat(
        'comp_type.categorie, MIN(comp_type.ordre_categorie) AS rang_categorie',
        array('matiere'=>$matiere),
        ' GROUP BY comp_type.categorie ORDER BY rang_categorie ASC, comp_type.categorie ASC'
    );
}

 public function nbCategorie($matiere){
    return (int)$this->requeteAgregat('COUNT(DISTINCT comp_type.categorie)', array('matiere'=>$matiere))[0][0];
}

     public function listeMatiere(){
    // ALPHABETIQUE, et c'est tout ce qu'on peut faire : il n'existe pas de
    // colonne `ordre_matiere`. Le glisser-deposer de la bibliotheque range les
    // competences dans leur categorie et les categories dans leur matiere, pas
    // les matieres entre elles. Sans ce tri, MySQL rendait l'ordre qui
    // l'arrangeait, qui pouvait changer d'un bulletin a l'autre.
    return $this->requeteAgregat('DISTINCT comp_type.matiere', array(), ' ORDER BY comp_type.matiere ASC');
  }

   public function nbMatiere(){
    return (int)$this->requeteAgregat('COUNT(DISTINCT comp_type.matiere)')[0][0];
  }


public function anneeScolaire(){
require_once dirname(__DIR__).'/periodes-bulletin.php';
return feAnneeBulletin(new DateTimeImmutable($this->debut_perio))['libelle'];
}



}

