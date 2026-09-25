function valider(){
			return false;
		}

var texte=document.getElementById('nom');
texte.value="";

var derniereValeur="";

var boucle=setInterval(function() {

var valeurActuelle=texte.value;

if (valeurActuelle.length>=3 && valeurActuelle===derniereValeur){

	clearInterval(boucle);

	document.getElementById('formsaisie').submit();

	function valider(){
		return true
	}

}else{

	derniereValeur=valeurActuelle;
}

},500);

texte.addEventListener('keydown',function(evenement){

	if(evenement.key==='Enter' && texte.value.length>0){

		clearInterval(boucle);
		document.getElementById('formsaisie').submit();
	}
});

document.addEventListener('click',function(evenement){

	// La douchette garde le champ principal prêt après un clic dans une zone
	// neutre, mais elle ne doit jamais voler le focus d'un contrôle manuel.
	// Sans ce garde-fou, le champ de recherche du référentiel s'ouvrait puis
	// perdait immédiatement le focus au profit du champ de code.
	var cible=evenement.target instanceof Element?evenement.target:null;
	if(cible&&cible.closest('input,button,a,select,textarea,label,summary,details,[contenteditable="true"],.v2-recherche-referentiel')){
		return;
	}

	texte.focus()
},false);
