<?php

namespace App\Controller;


use App\Entity\Breadcrumb;
use App\Entity\NodesBreadcrumbsRelation;
use App\Entity\TreeNode;
use App\Manager\BreadcrumbFileManager;
use App\Manager\BreadcrumbManager;
use App\Repository\BreadcrumbRepository;
use App\Repository\NodesBreadcrumbsRelationRepository;
use App\Repository\TreeNodeRepository;
use App\Service\NavigationFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Editor de migas.
 *
 * Este es el controlador de todas las acciones que puede llevar a cabo el editor
 * de migas. Esto incluye operaciones CRUD asi como las validaciones pertinentes
 * para cada accion y varios procesos que se lanzan de forma automatica como son
 * las regeneraciones de migas existentes.
 *
 * @author Jose Serrano, Jose Maria Casado
 *
 * @Route("/breadcrumbs")
 */
class BreadcrumbsController extends AbstractController
{
    private $nodoRepository = 'UEditNavigationBundle:TreeNode';
    private $breadcrumbRepository = 'UEditNavigationBundle:Breadcrumb';
    private $relationRepository = 'UEditNavigationBundle:NodesBreadcrumbsRelation';
    private $templatesRepository = 'UEditNavigationBundle:Templates';


    #[Route("/")]
    public function indexAction(NavigationFormatter $navigationFormatter): Response
    {

        //Nombre del portal que ejecuta el editor
        $data = array(
            'portalName' => $this->getParameter('portal_id'),
            'formats' => $navigationFormatter->getFormats()
        );

        return $this->render(
            'Default/index.html.twig',
            $data
        );
    }


    #[Route("/navigation_ws")]
    public function navigationWsAction(): Response
    {
        $data = array(
            'portalName' => $this->getParameter('portal_id')
        );

        return $this->render(
            'Default/miga_ws.html.twig',
            $data
        );
    }

    /**
     * Guarda un nodo nuevo o edita un nodo existente.
     *
     * En caso de editar un nodo existente, lanza el proceso de regeneracion
     * de las migas que incluyan el nodo editado.
     *
     * @author Jose Serrano
     *
     */
    #[Route("/save/tree")]
    public function saveOrEditTreeNodeAction(
        BreadcrumbFileManager $breadcrumbFileManager,
        Request $request,
        NavigationFormatter $navigationFormatter,
        BreadcrumbManager $breadcrumbManager,
        EventDispatcher $dispatcher,
        EntityManagerInterface $manager
    ): Response
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);
        $breadcrumbRepository = $manager->getRepository(Breadcrumb::class);
        $nodesBreadcrumbRelationRepository = $manager->getRepository(NodesBreadcrumbsRelation::class);

        $nodeData = $request->request->all();

        //Comprueba que recibimos todos los datos necesarios del nodo.
        if (!$this->areValidNodeKeys($nodeData)) {
            $msg = 'Faltan datos del nodo';

            return $this->getErrorResponse($msg);
        }
        //Si hay clave "id", es una edicion de un nodo existente
        //de lo contrario es una insercion.
        $newNode = (array_key_exists('id', $nodeData)) ? false : true;

        //Si el nodo tiene id, es que se quiere ACTUALIZAR, si no, es un nodo
        //nuevo y se quiere INSERTAR.
        if ($newNode == false && isset($nodeData['id'])) {
            try {
                //Actualiza.
                $migas = $treeNodeRepository
                    ->updateNode($nodeData);
                //Comprueba si hay migas por regenerar.
                if (empty($migas)) {
                    $hasMigas = false;
                } else {
                    $hasMigas = true;
                    //Regeneracion de las migas implicadas una a una.
                    foreach ($migas as $miga) {
                        $this->generateBreadcrumb(
                            $miga['ID_MIGA'],
                            $miga['ID_NODO'],
                            $miga['NOMBRE_MIGA'],
                            $navigationFormatter,
                            $breadcrumbManager,
                            $breadcrumbFileManager,
                            $dispatcher,
                            $treeNodeRepository,
                            $breadcrumbRepository,
                            $nodesBreadcrumbRelationRepository,
                            explode(',', $miga['OUTPUTS'])
                        );
                    }
                }
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }
        } else {
            try {
                if ((!isset($nodeData['padre_id'])
                    || $nodeData['padre_id'] == ''
                    || $nodeData['padre_id'] == null)
                    && $nodeData['portalId'] == null
                ) {
                    $nodeData['padre_id'] = null;
                    $nodeData['portalId'] = $this
                        ->container
                        ->getParameter('portal.id');
                    $nodeData['sectionId'] = $breadcrumbFileManager->normaliza($nodeData['nombre']);
                }
                //Inserta.
                $newNodeId = $treeNodeRepository
                    ->insertNode($nodeData);
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }
        }

        //Mensaje de exito segun la operacion.
        if ($newNode) {
            $msg = '{"exito":"Guardado con éxito", "id":'
                . $newNodeId
                . ', "nombre": "'
                . $nodeData['nombre']
                . '", "portalId": "'
                . $nodeData['portalId']
                . '",  "sectionId":"'
                . $nodeData['sectionId']
                . '"}';
        } elseif ($hasMigas) {
            $msg = $this->composeSuccessJson(
                'Nodo guardado y migas regeneradas con éxito'
            );
        } else {
            $msg = $this->composeSuccessJson('Guardado con éxito');
        }

        return $this->getSuccessfulResponse($msg);
    }

    /**
     * Guarda el nuevo orden de los nodos especificados por el cliente.
     *
     * @author Jose Serrano
     *
     */
    #[Route("/save/order")]
    public function saveTreeOrder(Request $request, EntityManagerInterface $manager): Response
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);

        $post = $request
            ->request
            ->all();
        //Comprobacion de que existe la clave "lista" en POST
        if (!array_key_exists('lista', $post)) {
            $msg = "No se encuentra el parametro: lista.";

            return $this->getErrorResponse($msg);
        }

        $orderArray = $post['lista'];

        //No hay datos en $_POST
        if (sizeof($orderArray) === 0) {
            $msg = "No se han recibido los datos necesarios para cambiar el orden.";

            return $this->getErrorResponse($msg);
        //Los datos no llevan el formato valido
        } elseif (!$this->isValidOrderArray($orderArray)) {
            $msg = "Los datos de los ordenes no llevan el formato correcto.";

            return $this->getErrorResponse($msg);
        } else {
            try {
                //Actualiza el orden
                $wasSuccessful = $treeNodeRepository
                    ->updateTreeOrder($orderArray);
            } catch (\Exception $e) {
                $this->getErrorResponse($e->getMessage());
            }

            if (!$wasSuccessful) {
                $msg = "Ha ocurrido un error al actualizar la base de datos.";

                return $this->getErrorResponse($msg);
            } else {
                $msg = "Orden actualizado correctamente.";

                return $this->getSuccessfulResponse(
                    $this->composeSuccessJson($msg)
                );
            }
        }
    }

    /**
     * Borra un nodo del arbol en base a su identificador.
     *
     *
     * @author Jose Serrano
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */

    #[Route("/erase/tree/{nodeId}")]
    public function eraseTreeAction($nodeId, EntityManagerInterface $manager): Response
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);
        try {
            //Intentamos borrar el nodo
            $removed = $treeNodeRepository
                ->removeNode($nodeId);
        } catch (\Exception $e) {
            //Condicion que se da cuando se intenta borrar un nodo
            //que es raiz de una miga pero sus hojas ya han sido borradas.
            //A falta de saber que hacer, mostramos este mensaje para que
            //no se vea un error de ORACLE. Cuando sepamos el uso,
            //modificaremos el comportamiento. Jose Serrano (19/9/13)
            if ($e->getCode() == 2292) {
                $message = 'No se puede borrar este nodo; contiene una miga';
            } else {
                $message = $e->getMessage();
            }

            return $this->getErrorResponse($message);
        }

        if ($removed) {
            $msg = 'Nodo ('.$nodeId.') borrado correctamente.';

            return $this->getSuccessfulResponse(
                $this->composeSuccessJson($msg)
            );
        //El nodo es padre y no puede ser borrado.
        } elseif ($removed === 0) {
            $msg = 'El nodo con id '.$nodeId.' tiene hijos y no puede ser borrado.';

            return $this->getErrorResponse($msg);
        //El nodo no existe.
        } else {
            $msg = 'El nodo con id '.$nodeId.' no existe.';

            return $this->getErrorResponse($msg);
        }
    }

    /**
     * Borra de disco y de base de datos la miga que se le pasa como parametro.
     * Este método no borra la entrada de la tabla publicación, ya que lo llamamos
     * nosotros desde la aplicación cuando regeneramos una miga, en ese caso lo
     * que hacemos es actualizar la entrada de esa tabla.
     *
     * Parametros:
     * @param Breadcrumb $breadcrumb El breadcrumb a borrar.
     * @param array $outputs El listado de salidas que el usuario ha marcado para regenerar
     *
     * @author Jose Maria Casado
     */
    private function eraseBreadcrumb(
        Breadcrumb $breadcrumb,
        array $outputs,
        BreadcrumbFileManager $breadcrumbFileManager,
        NavigationFormatter $navigationFormatter,
        $treeNodeRepository,
        $nodesBreadcrumbsRelationRepository    )
    {
        try {
            $listaNodosPadres = $treeNodeRepository
                ->getListNameOfPathParentsSimple($breadcrumb->getIdRaiz());
        } catch (\Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        $correcto = $breadcrumbFileManager->removeBreadcrumb($listaNodosPadres, $navigationFormatter->getFormats(), $outputs);

        if ($correcto) {
            try {
                $elements = $nodesBreadcrumbsRelationRepository
                    ->findBy(
                        array('idPublicacion' => $breadcrumb->getId())
                    );
                $nodesBreadcrumbsRelationRepository
                    ->eraseBreadcrumbRelationsBd($elements);
            } catch (\Exception $e) {
                throw new \Exception('Error al borrar en BBDD');
            }
        } else {
            throw new \Exception('Error al borrar en disco el fichero');
        }
    }

    /**
     * Borra completamente una miga, para la petición desde cliente, cuando un nodo se queda sin hijos y tenía miga
     * Incorpora el borrado del registro de la tabla publicación
     *
     * Parametros:
     * @param Breadcrumb $breadcrumb id de la miga que se genera
     *                               @author Jose Maria Casado
     */
    #[Route("/erase/breadcrumb")]
    public function eraseBreadcrumbUrl(
        BreadcrumbFileManager $breadcrumbFileManager,
        NavigationFormatter $navigationFormatter,
        Request $request,
        EntityManagerInterface $manager
    ): Response
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);
        $breadcrumbRepository = $manager->getRepository(Breadcrumb::class);
        $nodesBreadcrumbsRelationRepository = $manager->getRepository(NodesBreadcrumbsRelation::class);

        $parametros = $request
            ->request
            ->all();

        if (!$this->validateEraseBreadcrumb($parametros)) {
            $msg = 'Faltan datos para borrar la miga';

            return $this->getErrorResponse($msg);
        }

        try {
            $listaNodosPadres = $treeNodeRepository
                ->getListNameOfPathParentsSimple($parametros['id']);
        } catch (\Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        $formats = $navigationFormatter->getFormats();

        $correcto = $breadcrumbFileManager->removeBreadcrumb($listaNodosPadres, $formats);

        if ($correcto) {
            try {
                $elements = $nodesBreadcrumbsRelationRepository
                    ->findBy(
                        array('idPublicacion' => $parametros['miga_id'])
                    );
                $nodesBreadcrumbsRelationRepository
                    ->eraseBreadcrumbRelationsBd($elements);

                $breadcrumbs = $breadcrumbRepository
                    ->findBy(
                        array('id' => $parametros['miga_id'])
                    );
                $breadcrumbRepository
                    ->eraseBreadcrumb($breadcrumbs);
            } catch (\Exception $e) {
                $msg = "Error al borrar en BBDD";

                return $this->getErrorResponse($msg);
            }
        } else {
            $msg = "Error al borrar en disco";

            return $this->getErrorResponse($msg);
        }

        $msg = 'Miga ('.$parametros['miga_id'].') borrada correctamente.';

        return $this->getSuccessfulResponse(
            $this->composeSuccessJson($msg)
        );
    }

    /**
     * Recibe por POST la petición de generación de miga, recoge los parametros
     * y llama a una funcion interna que se encarga de la generación
     *
     * Parametros recibidos por POST:
     * @param string $idMiga id de la miga que se genera
     * @param string $idNodo id del nodo que se genera la miga
     * @param string $nombre nombre de la miga
     *
     * @author Jose Maria Casado
     *
     */
    #[Route("/generate/breadcrumb")]
    public function generateBreadcrumbAction(
        Request $request,
        NavigationFormatter $navigationFormatter,
        BreadcrumbManager $breadcrumbManager,
        BreadcrumbFileManager $breadcrumbFileManager,
        EventDispatcher $dispatcher,
        EntityManagerInterface $manager
    ): Response
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);
        $breadcrumbRepository = $manager->getRepository(Breadcrumb::class);
        $nodesBreadcrumbRelationRepository = $manager->getRepository(NodesBreadcrumbsRelation::class);

        $parametros = $request
            ->request
            ->all();

        $selectedOutputs = array();
        foreach ($parametros['outputs'] as $output) {
            $selectedOutputs[] = $output['name'];
        }

        if (!$this->validateBreadcrumb($parametros)) {
            $msg = 'Faltan datos de la miga';

            return $this->getErrorResponse($msg);
        } else {
            $textSend = $this->generateBreadcrumb(
                $parametros['miga_id'],
                $parametros['id'],
                $parametros['miga_nombre'],
                $navigationFormatter,
                $breadcrumbManager,
                $breadcrumbFileManager,
                $dispatcher,
                $treeNodeRepository,
                $breadcrumbRepository,
                $nodesBreadcrumbRelationRepository,
                $selectedOutputs
            );

            return $this->getSuccessfulResponse($textSend);
        }
    }

    /**
     * Generar la miga de un nodo y desencadena todas las operaciones necesarias
     * para dejar el sistema de forma consistente
     *
     * @author Jose Maria Casado
     *
     * @param string $idMiga     id de la miga que se genera
     * @param string $idNodo     id del nodo que se genera la miga
     * @param string $nombre     nombre de la miga
     *
     * @return string mensaje de exito o error
     */
    public function generateBreadcrumb(
        $idMiga,
        $idNodo,
        $nombre,
        $navigationFormatter,
        $breadcrumbManager,
        $breadcrumbFileManager,
        $dispatcher,
        $treeNodeRepository,
        $breadcrumbRepository,
        $nodeBreadcrumbRelationRepository,
        $outputs = null
    )
    {
        $correcto = true;
        $msg = "";
        $breadcrumb = null;

        if ($idNodo != "") {
            try {
                //Recupero la miga en base a su Id
                $breadcrumb = $breadcrumbRepository
                    ->findOneBy(array('idRaiz' => $idNodo));
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }
        }

        try {
            if (is_null($breadcrumb)) {
                //Como la miga no existe la creo
                $breadcrumb = new Breadcrumb();
                $breadcrumb->setIdRaiz($idNodo);
            } else {
                //Borramos la miga, tanto en base de datos como en disco
                $this->eraseBreadcrumb($breadcrumb, $outputs, $breadcrumbFileManager, $navigationFormatter, $treeNodeRepository, $nodeBreadcrumbRelationRepository);
            }

            try {
                //Saco un listado de nodos con todos los nodos
                //padres del nodo raiz de la miga que queremos crear
                $listaNodosPadres = $treeNodeRepository
                    ->getListNameOfPathParentsSimple($breadcrumb->getIdRaiz());
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }

            //Creo el path donde se guarda el include
            $path = $this
                ->getParameter('breadcrumbIncludePath')
                . $breadcrumbFileManager->createPath($listaNodosPadres);

            //Seteo el path de la miga en el objeto miga
            $breadcrumb->setPath($path);

            //guardo los outputs seleccionados.
            if ($outputs != null) {
                $breadcrumb->setOutputs(implode(',', $outputs));
            } else {
                $outputs = $breadcrumb->getOutputs();
            }

            //Solo cambio el nombre de la miga sino está vacía la variable $nombre
            if ($nombre !== "") {
                $breadcrumb->setNombre($nombre);
            }

            try {
                //Guardo la miga
                $breadcrumbRepository
                    ->saveBreadcrumb($breadcrumb);

                //Consigo los hijos de el nodo raiz de la miga
                $listaNodosHijos = $treeNodeRepository
                    ->getListNameOfPathSonsSimple($breadcrumb->getIdRaiz());

                $listaNodos = array_merge($listaNodosPadres, $listaNodosHijos);

                //Guardo las relaciones intermedias entre los nodos y la miga
                $nodeBreadcrumbRelationRepository
                    ->saveBreadcrumbRelationsBd(
                        $breadcrumb->getId(),
                        $listaNodos
                    );

                //Recupero el nodo padre de los nodos hoja
                $nodo = $treeNodeRepository
                    ->findOneById($idNodo);

                //Recupero la miga que acabo de insertar porque sino no tengo el ID
                $breadcrumb = $breadcrumbRepository
                    ->findOneBy(
                        array('idRaiz' => $breadcrumb->getIdRaiz())
                    );
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }

            $formats = $navigationFormatter->getFormats();

            $baseBreadCrumbFileName = $this->getParameter('breadcrumbFileName');

            foreach ($formats as $format) {
                foreach ($format->getOutputs() as $output) {
                    if (in_array($format->getName()."-".$output->getName(), $outputs)) {
                        $template = $this->renderTemplate(
                            $listaNodosPadres,
                            $listaNodosHijos,
                            $output->getTemplate(),
                            $nodo,
                            false,
                            $this
                                ->getParameter('breadcrumbSectionsInclude')
                        );

                        $breadcrumbFileName = $breadcrumbFileManager->generateFilename($baseBreadCrumbFileName, $output->getSuffix());

                        //Genero el fichero para web y para movil en disco con la miga
                        $correcto = $breadcrumbFileManager->writeBreadcrumb(
                            mb_convert_encoding($template, 'ISO-8859-1'),
                            $path,
                            $breadcrumbFileName
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            $correcto = false;
            $msg = $e->getMessage();
        }

        if ($correcto) {
            // Launch event to create continuous navgation related autocovers file.
            $event = new Breadcrumb($path, $listaNodosHijos);

            $dispatcher->dispatch('uedit_cms.post_breadcrumb_write', $event);

            return $this->composeExitoBreadcrumbJson(
                'Generado con exito',
                $breadcrumb
            );
        } else {
            return $this->composeErrorJson($msg);
        }
    }

    /**
     * Parsea un fichero TWIG con los datos de la miga y devuelve el HTML.
     * Hay dos tipos de generación dependiendo si es móvil o no
     *
     *
     * @return string El HTML de la vista ya parseada
     */
    private function renderTemplate(
        $listaNodosPadres,
        $listaNodosHijos,
        $template,
        $nodo,
        $isMobile,
        $pathIncludeSecciones = false
    ) {
        $nodeData = array(
            "nodosPadre" => $listaNodosPadres,
            "nodosHijo" => $listaNodosHijos,
            "idNodo" => $nodo->getId(),
            "nombre" => $nodo->getNombre(),
        );

        //Incluimos el path del fichero de secciones.
        //Hace falta porque al hacer el render de la plantilla movil, no
        //queremos la ruta del include.
        if (!$isMobile && $pathIncludeSecciones !== false) {
            $nodeData['pathIncludeSecciones'] = $pathIncludeSecciones;
        }

        return $this->renderView($template, $nodeData);
    }

    /**
     * Devuelve las secciones de un portal.
     * Se comunica con una API del Bridge de CMS que devuelve un  JSON
     * con las distintas secciones de un portal, así como otros datos asociados
     * (identificador del portal, identificador del documento de Mongo, etc)
     *
     * @author Jose Serrano
     *
     * @param string $portal el portal del cual se quieren las secciones
     */
    #[Route("/get/sections/{portal}")]
    public function getSectionsAction($portal, HttpClientInterface $client, EntityManagerInterface $manager)
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);

        $portalId = $this->getParameter('portalId');

        //Comprobacion de cadenas.
        if (!preg_match("/[a-zA-Z]/", $portal)) {
            $msg = 'El portal debe ser una cadena válida.';

            return $this->getErrorResponse($msg);
        }

        $sectionsUrl = $this->getParameter('loggingService') . "/portals/$portal/sections";

        try{
            $response = $client->request('GET', $sectionsUrl);

            $sectionData = $response->toArray();
        } catch (\Exception $e){
            return $this->getErrorResponse("Error al pedir las secciones de $portal");
        }

        if (is_null($sectionData['data']) || empty($sectionData['data'])) {
            $msg = 'No existen secciones o no existe el portal.';

            return $this->getErrorResponse($msg);
        }

        $sections = $treeNodeRepository
            ->getSectionsName($sectionData, $portalId);

        $sectionsJson = json_encode($sections);

        return $this->getSuccessfulResponse($sectionsJson);
    }

    /**
     * Esta accion devuelve un JSON con todas las secciones que tienen
     * alguna miga asociada.
     *
     * @author Jose Serrano
     *
     *
     * @return Response el JSON con las secciones
     */
    #[Route("/get/sections_with_breadcrumbs")]
    public function getSectionsWithBreadcrumbsJsonAction(EntityManagerInterface $manager): Response
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);

        try {
            $sectionsJson = json_encode(
                $treeNodeRepository
                    ->getSectionsWithBreadcrumbs()
            );
        } catch (\Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        return $this->getSuccessfulResponse($sectionsJson);
    }


    /**
     * Comprueba que la estructura para generar una miga que envía el cliente es
     * correcta
     *
     * @author Jose Maria Casado
     *
     * @param  array   $nodeArr Datos de una miga
     * @return boolean Si la estructura es correcta
     */
    private function validateBreadcrumb($nodeArr)
    {
        $mandatoryKeys = array(
            'miga_nombre',
            'miga_id',
            'id'
        );

        if (empty($nodeArr)) {
            return false;
        }

        foreach ($mandatoryKeys as $mandatoryKey) {
            if (!isset($nodeArr[$mandatoryKey])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Comprueba que la estructura para borrar una
     * miga en disco y en base de datos.
     *
     * @author Jose Maria Casado
     *
     * @param  array   $nodeArr Datos para borrar una miga
     * @return boolean Si la estructura es correcta
     */
    private function validateEraseBreadcrumb($nodeArr)
    {
        $mandatoryKeys = array('miga_id', 'id');

        if (empty($nodeArr)) {
            return false;
        }

        foreach ($mandatoryKeys as $mandatoryKey) {
            if (!isset($nodeArr[$mandatoryKey])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Comprueba que la estructura de los datos
     * de un nodo enviado por el cliente es correcta.
     *
     * @author Jose Serrano
     *
     * @param  array   $nodeArr Datos del nodo
     * @return boolean Si la estructura es correcta
     */
    private function areValidNodeKeys($nodeArr)
    {
        //Todos los datos de un nodo excepto el Id
        $mandatoryKeys = array(
            'nombre',
            'url',
            'padre_id',
            'visible'
        );

        if (empty($nodeArr)) {
            return false;
        }
        foreach ($mandatoryKeys as $mandatoryKey) {
            //Si falta una clave, salimos con error
            if (!isset($nodeArr[$mandatoryKey])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Comprueba que el array de ordenes enviado por el cliente lleva
     * el formato correcto.
     *
     * a( a( 'id' => i, 'orden' =>j),
     *    a( 'id' => x, 'orden' => y)
     *    ...)
     *
     * @author Jose Serrano
     *
     * @param  array   $nodeArray El array con los ids y los nuevos ordenes
     * @return boolean Si la estructura es correcta
     */
    private function isValidOrderArray($nodeArray)
    {
        foreach ($nodeArray as $nodeData) {
            //Comprobamos que tenemos las dos claves
            //dentro del elemento
            if (array_key_exists('id', $nodeData)
               && array_key_exists('orden', $nodeData)) {
                continue;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Compone una cadena de texto en JSON para devolver los mensajes
     * de error al cliente.
     *
     * @author Jose Serrano
     *
     * @param  string $errorMsgBody El mensaje de error
     * @return string JSON con el mensaje de error
     */
    private function composeErrorJson($errorMsgBody)
    {
        return '{"error": "' . $errorMsgBody . '"}';
    }

    /**
     * Compone una cadena de texto en JSON para devolver los mensajes
     * de confirmacion al cliente.
     *
     * @author Jose Maria Casado
     *
     * @param  string $confirmMsgBody El mensaje de confirmacion
     * @param  string $idNodo         El id del nodo
     * @return string JSON con el mensaje de confirmacion
     */
    private function composeConfirmJson($confirmMsgBody, $idNodo)
    {
        return '{"confirm": "'
            . $confirmMsgBody
            . '", "nodo_id": "'
            . $idNodo
            . '"}';
    }

    /**
     * Compone una cadena de texto en JSON para devolver los mensajes correctos
     * al cliente en el caso de que se genere una miga.
     *
     * @author Jose Maria Casado
     *
     * @param string     $texto      El mensaje de proceso correcto.
     * @param Breadcrumb $breadcrumb Objeto con los datos de la miga generada
     *
     * @return string JSON con el mensaje de exito.
     */
    private function composeExitoBreadcrumbJson($texto, $breadcrumb)
    {
        return '{"exito": "'
            . $texto
            . '", "miga": {"id":"'
            . $breadcrumb->getId()
            . '", "path":"'
            . $breadcrumb->getPath()
            . '" }}';
    }

    /**
     * Compone una cadena de texto en JSON para devolver los mensajes
     * de error al cliente.
     *
     * @author Jose Serrano
     *
     * @param  string $errorMsgBody El mensaje de error
     * @return string JSON con el mensaje de error
     */
    private function composeSuccessJson($successMsgBody)
    {
        return '{"exito": "' . $successMsgBody . '"}';
    }

    /**
     * Recupera el arbol de categorias por cada seccion.
     * Espera dos parametros por POST: id y nombre. Son el Id de la seccion
     * y el nombre de la misma - por si desde el CMS se ha cambiado el nombre
     * y tenemos que actualizarlo.
     *
     * @author Jose Serrano
     * @author Jose Maria Casado
     *
     * @param string $portalId  portalId (del Bridge CMS) de la seccion
     * @param string $sectionId sectionId (del Bridge CMS) de la seccion
     * @param string $nombre    nombre de seccion
     */
    #[Route("/get/tree")]
    public function getTreeAction(EntityManagerInterface $manager, Request $request): Response
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);

        $request = $request
            ->request
            ->all();

        $portalId = $request['portalId'];
        $sectionId = $request['sectionId'];
        $name = $request['nombre'];

        if (!preg_match("/[a-zA-Z]/", $name)) {
            $msg = 'El nombre de la seccion no puede estar vacío';

            return $this->getErrorResponse($msg);
        }

        try {
            $element = $treeNodeRepository
                ->findBy(
                    array(
                        'portalId' => $portalId,
                        'sectionId' => $sectionId,
                    )
                );
        } catch (\Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        if ($element != null) {
            $idNodo = $element[0]->getId();

            if (trim($name)!= trim($element[0]->getNombre())) {
                $msg = 'Actualizar migas hijas.';

                return new Response(
                    $this->composeConfirmJson($msg, $idNodo),
                    200,
                    $this->getResponseHeaders()
                );
            }
        } else {
            $node['nombre'] =  urldecode($name);
            $node['url'] = '';
            $node['visible'] = true;
            $node['padre_id'] = null;
            $node['portalId'] = $portalId;
            $node['sectionId'] = $sectionId;

            try {
                $idNodo = $treeNodeRepository
                    ->insertNode($node);
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }
        }

        try {
            $treeJson = $treeNodeRepository
                ->getTreeJsonByNodoId($idNodo);
        } catch (\Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        if ($treeJson === "null") {
            $msg = 'No existe un arbol para esa sección.';

            return $this->getErrorResponse($msg);
        } else {
            return $this->getSuccessfulResponse($treeJson);
        }
    }

    /**
     * Actualiza el nodo padre y regenera todas sus migas hijas.
     *
     * @author Jose Maria Casado
     *
     * @param string $id   Id Mongo de la base de datos
     * @param string $name Nombre de la sección
     */
    #[Route("/update/regenerate/tree")]
    public function updateRegenerateTreeAction(
        Request $request,
        NavigationFormatter $navigationFormatter,
        BreadcrumbManager $breadcrumbManager,
        BreadcrumbFileManager $breadcrumbFileManager,
        EventDispatcher $dispatcher,
        EntityManagerInterface $manager
    )
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);
        $breadcrumbRepository = $manager->getRepository(Breadcrumb::class);
        $nodeBreadcrumbRelationRepository = $manager->getRepository(NodesBreadcrumbsRelation::class);

        $request = $request
            ->request
            ->all();

        $id =  $request['id'];
        $name = $request['nombre'];

        if ($id != "") {
            try {
                //Recupero el nodo que tengo que actualizar
                $nodo = $treeNodeRepository
                    ->findOneById($id);
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }
        } else {
            $msg = "No se han recibido los datos necesarios para regenerar.";

            return $this->getErrorResponse($msg);
        }

        $node = null;

        if ($nodo == null) {
            $msg = 'El nodo con id '.$id.' no existe.';

            return $this->getErrorResponse($msg);
        } else {
            $node['nombre'] = $name;
            $node['url'] = $nodo->getUrl();
            $node['visible'] =   $nodo->getVisible();
            $node['padre_id'] = 0;
            $node['id'] = $nodo->getId();
        }
        try {
            //Actualiza.
            $migas = $treeNodeRepository
                ->updateNode($node);

            //Comprueba si hay migas por regenerar.
            if (empty($migas)) {
                $hasMigas = false;
            } else {
                $hasMigas = true;

                foreach ($migas as $miga) {
                    $this->generateBreadcrumb(
                        $miga['ID_MIGA'],
                        $miga['ID_NODO'],
                        $miga['NOMBRE_MIGA'],
                        $navigationFormatter,
                        $breadcrumbManager,
                        $breadcrumbFileManager,
                        $dispatcher,
                        $treeNodeRepository,
                        $breadcrumbRepository,
                        $nodeBreadcrumbRelationRepository
                    );
                }
            }
        } catch (\Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }
        //Mensaje de exito segun la operacion.
        if ($hasMigas) {
            $msg = $this->composeSuccessJson(
                'Nodo guardado y migas regeneradas con éxito'
            );
        } else {
            $msg = $this->composeSuccessJson('Guardado con éxito');
        }

        return new Response(
            $msg,
            200,
            array(
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*'
            )
        );
    }

    /**
     * Recupera la estructura de miga de un nodo en concreto
     *
     * @author Jose Maria Casado
     * @param string $id id del nodo
     */
    #[Route("/get/json/breadcrumb/{id}")]
    public function getJsonBreadcrumbById($id, EntityManagerInterface $manager)
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);
        try {
            //Saco un listado de nodos con todos los padres de un nodo
            $listaNodosPadres = $treeNodeRepository
                ->getListNameOfPathParentsComplete($id);

            //Consigo los hijos de el nodo principal de la miga
            $listaNodosHijos = $treeNodeRepository
                ->getListNameOfPathSonsComplete($id);

        } catch (\Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        $listaNodos = array_merge($listaNodosPadres, $listaNodosHijos);

        try {
            $treeJson = $treeNodeRepository
                ->createTree($listaNodos, 0);
        } catch (\Exception $e) {
            $msg = 'No existe un arbol para esa sección.';

            return $this->getErrorResponse($msg);
        }

        if ($treeJson==null) {
            $msg = 'No existe un arbol para esa sección.';

            return $this->getErrorResponse($msg);
        } else {
            return new Response(
                json_encode($treeJson),
                200,
                $this->getResponseHeaders()
            );
        }
    }

    /**
     * Recupera el path en un json de una miga web
     *
     * @author Jose Maria Casado
     * @param string $id id de la miga
     */
    #[Route("/get/json/path/web/{id}")]
    public function getJsonPathByIdMigaWeb($id, EntityManagerInterface $manager): Response
    {
        $breadcrumbRepository = $manager->getRepository(Breadcrumb::class);
        return $this->getJsonPathByIdMiga($id, false, $breadcrumbRepository);
    }

    /**
     * Recupera el path en un json de una miga mobile
     *
     * @author Jose Maria Casado
     * @param string $id id de la miga
     */
    #[Route("/get/json/path/mobile/{id}")]
    public function getJsonPathByIdMigaMobile($id, EntityManagerInterface $manager): Response
    {
        $breadcrumbRepository = $manager->getRepository(Breadcrumb::class);
        return $this->getJsonPathByIdMiga($id, true, $breadcrumbRepository);
    }

    /**
     * Recupera el path en un json de una miga
     *
     *
     * @author Jose Maria Casado
     * @param string  $id     id de la miga
     * @param boolean $mobile si miga web o movil
     */
    private function getJsonPathByIdMiga($id, $mobile, $breadcrumbRepository)
    {
        if ("" != $id) {
            try {
                //Recupero la miga en base a su Id
                $breadcrumb = $breadcrumbRepository
                    ->findOneBy(array('idRaiz' => $id));
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }
        } else {
            $breadcrumb = null;
        }

        if ($breadcrumb == null) {
            $msg = 'No existe miga para ese nodo.';

            return $this->getErrorResponse($msg);
        }

        $fichero = $mobile
            ? $this->getParameter('breadcrumbMobileFilename')
            : $this->getParameter('breadcrumbDesktopFilename');

        $msg = $this->composeSuccessJson($breadcrumb->getPath() . $fichero);

        return new Response(
            $msg,
            200,
            $this->getResponseHeaders()
        );
    }

    /**
     * Recupera el arbol de categorias por cada seccion.
     * Espera dos parametros por GET: id. Son el Id de la seccion.
     *
     * @author Jose Maria Casado
     *
     * @param string $portalId  (del Bridge CMS) del portal
     * @param string $sectionId (del Bridge CMS) de la seccion
     */
    #[Route("/get/search/tree/{portalId}/{sectionId}")]
    public function getTreeSearchAction($portalId, $sectionId, EntityManagerInterface $manager)
    {
        $treeNodeRepository = $manager->getRepository(TreeNode::class);

        if ($portalId !== "" || $sectionId !== "") {
            try {
                //Recupero el nodo raíz en base
                //al portalId y al sectionId
                $element = $treeNodeRepository
                    ->findBy(
                        array(
                            'portalId' => $portalId,
                            'sectionId' => $sectionId
                        )
                    );
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }
        } else {
            $element = null;
        }

        if ($element != null) {
            $idNodo = $element[0]->getId();
            try {
                $treeJson = $treeNodeRepository
                    ->getTreeJsonByNodoId($idNodo);
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }

            return new Response(
                $treeJson,
                200,
                $this->getResponseHeaders()
            );
        } else {
            $msg = 'No existe un arbol para esa sección.';

            return $this->getErrorResponse($msg);
        }
    }

    /**
     * Recupera el HTML de una miga web
     *
     * @author Jose Maria Casado
     * @param string $id id de la miga
     */
    #[Route("/get/html/web/{id}")]
    public function getHTMLByIdMigaWeb($id, EntityManagerInterface $manager): Response
    {
        $breadcrumbRepository = $manager->getRepository(Breadcrumb::class);
        return $this->getHTMLByIdMiga($id, false, $breadcrumbRepository);
    }

    /**
     * Recupera el HTML de una miga móvil
     *
     * @author Jose Maria Casado
     * @param string $id id de la miga
     */
    #[Route("/get/html/mobile/{id}")]
    public function getHTMLByIdMigaMobile($id, EntityManagerInterface $manager): Response
    {
        $breadcrumbRepository = $manager->getRepository(Breadcrumb::class);
        return $this->getHTMLByIdMiga($id, true, $breadcrumbRepository);
    }

    /**
     * Recupera el HTML de una miga web o mobile
     *
     *
     * @author Jose Maria Casado
     * @param string  $id     id de la miga
     * @param boolean $mobile si miga web o movil
     */
    private function getHTMLByIdMiga($id, $mobile, $breadcrumbRepository)
    {
        if ("" != $id) {
            try {
                $breadcrumb = $breadcrumbRepository
                    ->findOneBy(array('idRaiz' => $id));
            } catch (\Exception $e) {
                return $this->getErrorResponse($e->getMessage());
            }
            //Recupero la miga en base a su Id
        } else {
            $breadcrumb = null;
        }

        if ($breadcrumb != null) {
            $fichero = $mobile
                ? $this->getParameter('breadcrumbDesktopFileName')
                : $this->getParameter('breadcrumbMobileFileName');

            try {
                $contenido = file_get_contents($breadcrumb->getPath() . $fichero);
            } catch (\Exception $e) {
                $msg = 'Ha ocurrido algún error al recuperar el html';

                return $this->getErrorResponse($msg);
            }

            if ($contenido != '') {
                return new Response(
                    $contenido,
                    200,
                    $this->getResponseHeaders()
                );
            } else {
                $msg = 'El contenido del fichero html está vacío.';

                return $this->getErrorResponse($msg);
            }

        } else {
            $msg = 'No existe miga para ese nodo.';

            return $this->getErrorResponse($msg);
        }
    }

    private function getResponseHeaders(): array
    {
        return array(
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*'
        );
    }

    private function getErrorResponse($msg): Response
    {
        return new Response(
            $this->composeErrorJson($msg),
            200,
            $this->getResponseHeaders()
        );
    }

    private function getSuccessfulResponse($payload): Response
    {
        return new Response(
            $payload,
            200,
            $this->getResponseHeaders()
        );
    }
}
