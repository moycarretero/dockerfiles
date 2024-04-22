<?php

namespace App\Repository;


use Doctrine\ORM\EntityRepository;
use App\Entity;

/**
 * NodoRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TreeNodeRepository extends EntityRepository {

    /**
     * Recoge los nodos correspondientes al arbol de la seccion, compone una
     * estructura jerarquica en base al resultado de los datos y la convierte
     * al formato JSON.
     *
     * @author Jose Serrano, Jose Maria Casado
     *
     * @param string $section seccion - raiz del arbol
     *
     * @return string $tree JSON con los nodos
     */
    public function getTreeJsonBySection($section)
    {
        //Recogemos el identificador de la raiz
        //para usarlo como filtro en vez de usar el nombre
        //que puede estar repetido.
        $query = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT nodos.id
                FROM editor_migas_nodos nodos
                WHERE nodos.padre_id IS NULL
                AND nodos.nombre = '" . $section . "'"
            );

        $nodeData = $query->fetchAll(\PDO::FETCH_ASSOC);

        if(empty($nodeData) || is_null($nodeData)) {
            throw new \Exception(
                'No existe ninguna sección con ese nombre'
            );
        }

        $nodoId = $nodeData[0]['ID'];

        //SQL nativo para aprovechar la construccion
        //START WITH...CONNECT BY PRIOR de Oracle
        $treeResults = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT nodos.id,
                nodos.nombre,
                nodos.url,
                nodos.padre_id,
                nodos.visible,
                nodos.orden,
                nodos.portal_id,
                nodos.section_id,
                publicacion.id as miga_id,
                publicacion.nombre as miga_nombre
                FROM editor_migas_nodos nodos
                LEFT JOIN editor_migas_publicaciones publicacion
                ON nodos.id = publicacion.id_raiz
                START WITH nodos.id = '" . $nodoId . "'
                CONNECT BY PRIOR nodos.ID = nodos.PADRE_ID
                ORDER SIBLINGS BY nodos.orden"
            );

        $results = $treeResults->fetchAll(\PDO::FETCH_ASSOC);

        $tree = $this->createTree($results, 0);

        return json_encode($tree);
    }

    /**
     * Devuelve un array con el portalId, sectionId y el nombre de aquellas secciones que
     * tengan migas asociadas.
     *
     * @author Jose Serrano
     *
     * @return array portalId, sectionId y nombre de las secciones con migas asociadas.
     */
    public function getSectionsWithBreadcrumbs()
    {
        $sectionsWithBreadcrumbs = array();

        $qb = $this->createQueryBuilder('TreeNode');
        $qb->select('t')
           ->from('UEdit\Bundle\NavigationBundle\Entity\TreeNode', 't')
           ->where('t.padreId IS NULL')
           ->andWhere('t.portalId IS NOT NULL')
           ->andWhere('t.sectionId IS NOT NULL');

        $sections = $qb->getQuery()->execute();

        if(empty($sections)) {
            throw new \Exception('No hay migas o secciones creadas.');
        }

        foreach ($sections as $section) {
            $treeResults = $this->getEntityManager()
                ->getConnection()
                ->executeQuery(
                    "SELECT nodos.id,
                    nodos.nombre,
                    nodos.url,
                    nodos.padre_id,
                    nodos.visible,
                    nodos.orden,
                    nodos.portal_id,
                    nodos.section_id,
                    publicacion.id as miga_id,
                    publicacion.nombre as miga_nombre
                    FROM editor_migas_nodos nodos
                    LEFT JOIN editor_migas_publicaciones publicacion
                    ON nodos.id = publicacion.id_raiz
                    WHERE publicacion.id IS NOT NULL
                    START WITH nodos.id = '" . $section->getId() . "'
                    CONNECT BY PRIOR nodos.ID = nodos.PADRE_ID
                    ORDER SIBLINGS BY nodos.orden"
               );

            $results = $treeResults->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($results)) {
                array_push(
                    $sectionsWithBreadcrumbs,
                    array(
                        'portalId' => $section->getPortalId(),
                        'sectionId' => $section->getSectionId(),
                        'nombre' => $section->getNombre()
                    )
                );
            }
        }

        return $sectionsWithBreadcrumbs;
    }

    public function getTreeJsonByNodoId($id) {
        //SQL nativo para aprovechar la construccion
        //START WITH...CONNECT BY PRIOR de Oracle
        $query = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT nodos.id,
                nodos.nombre,
                nodos.url,
                nodos.padre_id,
                nodos.visible,
                nodos.orden,
                nodos.portal_id,
                nodos.section_id,
                publicacion.id as miga_id,
                publicacion.nombre as miga_nombre
                FROM editor_migas_nodos nodos
                LEFT JOIN editor_migas_publicaciones publicacion
                ON nodos.id = publicacion.id_raiz
                START WITH nodos.id = " . $id . "
                CONNECT BY PRIOR nodos.ID = nodos.PADRE_ID
                ORDER SIBLINGS BY nodos.orden"
            );

        $results = $query->fetchAll(\PDO::FETCH_ASSOC);

        $tree = $this->createTree($results, 0);

        return json_encode($tree);
    }

    /**
     * Inserta un nodo nuevo en la tabla de nodos.
     *
     * @author Jose Serrano
     *
     * @param array $node datos del nodo
     *
     * @return boolean
     */
    public function insertNode($node)
    {
        $id = null;

        $treeNode = new Entity\TreeNode();
        $treeNode->setNombre($node['nombre']);
        $treeNode->setUrl($node['url']);
        $treeNode->setVisible($node['visible']);
        $treeNode->setPadreId($node['padre_id']);
        $treeNode->setPortalId($node['portalId']);
        $treeNode->setSectionId($node['sectionId']);

        try {
            $em = $this->getEntityManager();
            $em->persist($treeNode);
            $em->flush();
        } catch (\OptimisticLockException $e) {
            throw new \Exception(
                'Ha ocurrido un error al insertar el nuevo nodo'
            );
        }

        try {
            $qb = $this->createQueryBuilder('TreeNode');


            //Comprueba que existe el portalId y el sectionId en $node
            if ((!array_key_exists('portalId', $node) || $node['portalId'] === '')
                || (!array_key_exists('sectionId', $node) || $node['sectionId'] === '')) {
                $exists = false;
            } else {
                $exists = true;
            }

            if (!$exists) {
                $qb->select('t.id')
                    ->from('UEdit\Bundle\NavigationBundle\Entity\TreeNode', 't')
                    ->where('t.padreId = :padre_id')
                    ->andWhere('t.url = :url')
                    ->andWhere('t.nombre = :nombre')
                    ->andWhere('t.visible = :visible')
                    ->setParameter('padre_id', $node['padre_id'])
                    ->setParameter('url', $node['url'])
                    ->setParameter('nombre', $node['nombre'])
                    ->setParameter('visible', $node['visible']);
            } else {
                $qb->select('t.id')
                   ->from('UEdit\Bundle\NavigationBundle\Entity\TreeNode', 't')
                   ->where('t.portalId = :portalId')
                   ->andWhere('t.sectionId = :sectionId')
                   ->setParameter('portalId', $node['portalId'])
                   ->setParameter('sectionId', $node['sectionId']);
            }

            $query = $qb->getQuery()->execute();

            if(!empty($query)) {
                //El id del nodo recien insertado.
                $id = $query[0]['id'];
            } else if(sizeof($query) > 1) {
                throw new \Exception(
                    'Al recuperar el nodo insertado, '
                    . 'se ha encontrado más de un nodo con los mismos valores.'
                );
            } else {
                throw new \Exception(
                    'No se encuentra el nodo recién insertado.'
                    );
            }
        } catch(\Exception $e) {
            throw new \Exception(
                'No se ha podido leer el nodo recién insertado.'
            );
        }

        return $id;
    }

    /**
     * Actualiza una tupla de nodo.
     *
     * @author Jose Serrano
     *
     * @param array $node datos del nodo
     */
    public function updateNode($node) {
        $editedNodeId = $node['id'];

        //Actualiza el nodo.
        $updateNode = $this->createQueryBuilder('TreeNode')
            ->update('UEdit\Bundle\NavigationBundle\Entity\TreeNode n')
            ->set('n.nombre', ':nombre')
            ->set('n.url', ':url')
            ->set('n.visible', ':visible')
            ->where('n.id = :id')
            ->setParameter('nombre', $node['nombre'])
            ->setParameter('url', $node['url'])
            ->setParameter('visible', $node['visible'])
            ->setParameter('id', $editedNodeId);

        //Comprobacion por si estamos editando el nodo raiz.
        if ($node['padre_id'] == 0) {
            $updateNode->set('n.padreId', ':padre_id');
            $updateNode->setParameter('padre_id', null);
        } else {
            $updateNode->set('n.padreId', ':padre_id');
            $updateNode->setParameter('padre_id', $node['padre_id']);
        }

        $wasSuccessful = $updateNode->getQuery()->execute();

        //El cliente nos ha mandado datos que no coinciden con ningun nodo.
        if ($wasSuccessful !== 1) {
            throw new \Exception('No existen nodos con id ' . $editedNodeId);
        //Devolvemos un array con los datos de los nodos que tienen migas
        //y que implican al nodo editado para poder regenerarlas y así reflejar
        //los datos nuevos.
        } else {
            //Buscamos las migas que contengan al nodo editado.
            $query = $this
                ->getEntityManager()
                ->getConnection()
                ->executeQuery(
                    "SELECT relacion.id_publicacion as id_miga,
                    publicacion.nombre as nombre_miga,
                    publicacion.id_raiz as id_nodo,
                    publicacion.id_plantilla as id_plantilla,
                    publicacion.outputs as outputs
                    FROM editor_migas_relaciones relacion
                    INNER JOIN editor_migas_publicaciones publicacion
                    ON relacion.id_publicacion = publicacion.id
                    WHERE relacion.id_nodo = " . $editedNodeId
                );

        $results = $query->fetchAll(\PDO::FETCH_ASSOC);

        if (!is_array($results)) {
            throw new \Exception(
                'Ha ocurrido un fallo al recuperar las migas'
            );
        }

        //Seteamos la variable a un array
        //vacio para estar seguros.
        if(empty($results)) {
            $results = array();
        }

        return $results;
        }
    }

    /**
     * Actualiza el orden de los nodos identificados en el array
     * enviado por el cliente.
     *
     * @author Jose Serrano
     *
     * @param array $orderArray identificador y orden de un nivel del arbol
     * @return bool Se ha actualizado el orden
     */
    public function updateTreeOrder(Array $orderArray) {
        $wasSuccessful = true;
        //Cada elemento contiene dos subelementos:
        //id y orden.
        foreach ($orderArray as $orderElement) {

            //Mientras no haya habido error seguimos
            if ($wasSuccessful) {
                $qb = $this->createQueryBuilder('TreeNode');
                $query = $qb->update('UEdit\Bundle\NavigationBundle\Entity\TreeNode n')
               ->set('n.orden', ':orden')
               ->where('n.id = :idNodo')
               ->setParameter('idNodo', $orderElement['id'])
               ->setParameter('orden', $orderElement['orden']);
            } else {
                break;
            }

            $wasSuccessful = $query->getQuery()->execute();
        }

        return $wasSuccessful;
    }

    /**
     * Borra un nodo de la tabla de nodos.
     *
     * @author Jose Serrano
     *
     * @param integer $nodeId Id del nodo a borrar
     *
     * @return boolean $wasSuccessful
     */
    public function removeNode($nodeId) {
        $wasSuccessful = false;

        if (!$this->isLeafNode($nodeId)) {
            return 0;
        } else {
            //Primero comprobamos si ese nodo está en alguna miga
            $elements = $this->getEntityManager()
                ->getRepository('UEditNavigationBundle:NodesBreadcrumbsRelation')
                ->breadcrumbsId($nodeId);

            //Si existe la relación las borramos para poder borrar el nodo
            if (!empty($elements)) {
                $this->getEntityManager()
                    ->getRepository('UEditNavigationBundle:NodesBreadcrumbsRelation')
                    ->eraseBreadcrumbRelationsBd($elements);
            }

            $qb = $this->createQueryBuilder('TreeNode');

            $qb->delete('UEdit\Bundle\NavigationBundle\Entity\TreeNode', 't')
               ->where('t.id = :id')
               ->setParameter('id', $nodeId);

            $results = $qb->getQuery()->execute();

            if(!empty($results)) {
                $wasSuccessful = true;
            }

            return $wasSuccessful;
        }
    }

    /**
     * Comprueba si un nodo es una hoja o por el contrario es padre.
     *
     * @author Jose Serrano
     *
     * @param int $nodeId El identificador del nodo
     *
     * @return boolean si es hoja o es padre
     */
    private function isLeafNode($nodeId) {
        $isLeaf = false;

        $qb = $this->createQueryBuilder('TreeNode');

        $qb->select('t.id')
           ->from('UEdit\Bundle\NavigationBundle\Entity\TreeNode', 't')
           ->where('t.padreId = :nodeId')
           ->setParameter('nodeId', $nodeId);

        $results = $qb->getQuery()->execute();

        //Si no hay resultados,
        //es que el nodo es una hoja
        if (empty($results)) {
            $isLeaf = true;
        }

        return $isLeaf;
    }

    /**
     * Crea una estructura de arbol dado un array de nodos basandose en los
     * identificadores para recorrer la estructura y crear una jerarquia.
     *
     * @author Jose Maria Casado
     *
     * @param array $listaNodos arreglo con los nodos del arbol
     * @param integer $posicion índice del para ir recorriendo el arreglo
     * de los nodos del árbol. Inicialmente empezará en 0.
     *
     * @return array $tree nodos ordenados por jerarquia
     */
    public function createTree($listaNodos, $posicion)
    {
        if (sizeof($listaNodos) > $posicion) {
            $tree = $this->createNodeStructure($listaNodos[$posicion]);
            $padreId = $listaNodos[$posicion]['ID'];

            for ($i = $posicion++; $i < sizeof($listaNodos); $i++) {
                if ($listaNodos[$i]['PADRE_ID'] == $padreId) {
                    $tree['hijos'][] = $this->createTree($listaNodos, $i);
                }
            }

            if (!isset($tree['hijos'])) {
                $tree['hijos'] = array();
            }

        } else {
            $tree = null;
        }

        return $tree;
    }

    /**
     * Crea la estructura, en forma de arreglo, de un nodo y asigna un valor
     * a las claves.
     *
     * @author Jose Maria Casado
     *
     * @param array $node los datos del nodo segun salen de la base de datos
     * @return array $nodeStructure la estructura del nodo con valores
     */
    private function createNodeStructure($node)
    {
        $nodeStructure['id'] = $node['ID'];
        $nodeStructure['padre_id'] = (isset($node['PADRE_ID']))
            ? $node['PADRE_ID']
            : null;
        $nodeStructure['url'] = $node['URL'];
        $nodeStructure['nombre'] = $node['NOMBRE'];
        $nodeStructure['visible'] = $node['VISIBLE'];
        $nodeStructure['miga_id'] = (isset($node['MIGA_ID']))
            ? $node['MIGA_ID']
            : null;
        $nodeStructure['miga_nombre'] = (isset($node['MIGA_NOMBRE']))
            ? $node['MIGA_NOMBRE']
            : null;
        $nodeStructure['orden'] = $node['ORDEN'];
        $nodeStructure['portal_id'] = (isset($node['PORTAL_ID']))
            ? $node['PORTAL_ID']
            : null;
        $nodeStructure['section_id'] = (isset($node['SECTION_ID']))
            ? $node['SECTION_ID']
            : null;

        return $nodeStructure;
    }

    /**
     * Recoge los nodos hijos implicados en una miga. Los devuelve en orden
     * ascendente.
     *
     * @author  Jose Maria Casado
     *
     * @param int $id Identificador del nodo que se genera la miga
     * @param boolean $completo Para saber que query tengo que construir
     *
     * @return array $result Array con los nodos
     */
    private function getListNameOfPathSons($id, $completo)
    {
        $consultaCompleta = $completo
            ? " ,nodos.padre_id, nodos.visible,
            nodos.orden, nodos.portal_id, nodos.section_id,
            publicacion.id as miga_id,
            publicacion.nombre as miga_nombre
            FROM editor_migas_nodos nodos
            LEFT JOIN editor_migas_publicaciones publicacion
            ON nodos.id = publicacion.id_raiz "
            : " FROM editor_migas_nodos nodos ";

        $query = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT nodos.nombre,
                nodos.id,
                nodos.url " . $consultaCompleta
                . " WHERE PADRE_ID = '" . $id
                . "'AND VISIBLE = 1
                order by orden asc"
            );

        $result = $query->fetchAll();

        return $result;
    }

    /**
     * Llama a la función getListNameOfPathSons pasandole un true
     *
     * @author  Jose Maria Casado
     *
     * @param int $id Identificador del nodo que se genera la miga
     *
     * @return array $result Array con los nodos
     */
    public function getListNameOfPathSonsComplete($id)
    {
        return $this->getListNameOfPathSons($id, true);
    }

    /**
     * Llama a la función getListNameOfPathSons pasandole un false
     *
     * @author  Jose Maria Casado
     *
     * @param int $id Identificador del nodo que se genera la miga
     *
     * @return array $result Array con los nodos
     */
    public function getListNameOfPathSonsSimple($id)
    {
        return $this->getListNameOfPathSons($id, false);
    }

    /**
     * Recoge los nodos implicados en una miga. Los devuelve en orden
     * descendente, empezando por el nodo raíz (padre de padres) y terminando
     * en los nodos hijos del id del nodo pasado como parametro.
     *
     * @author  Jose Maria Casado
     *
     * @param int $id Identificador del nodo que se genera la miga
     * @param boolean $completo Para saber que query tengo que construir
     *
     * @return array $result Array con los nodos
     */
    private function getListNameOfPathParents($id, $completo)
    {
        $consultaCompleta = $completo
            ? " ,nodos.padre_id, nodos.visible,
            nodos.orden, nodos.portal_id, nodos.section_id,
            publicacion.id as miga_id,
            publicacion.nombre as miga_nombre
            FROM editor_migas_nodos nodos
            LEFT JOIN editor_migas_publicaciones publicacion
            ON nodos.id = publicacion.id_raiz "
            : " FROM editor_migas_nodos nodos ";

        $query = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                "SELECT nodos.nombre,
                nodos.id,
                nodos.url " . $consultaCompleta . "
                START WITH nodos.id = '" . $id . "'
                CONNECT BY nodos.ID = PRIOR nodos.PADRE_ID
                order by level desc"
            );

        $result = $query->fetchAll();

        return $result;
    }

    /**
     * Llama a la función getListNameOfPathParents pasandole un true
     *
     * @author  Jose Maria Casado
     *
     * @param int $id Identificador del nodo que se genera la miga
     *
     * @return array $result Array con los nodos
     */
    public function getListNameOfPathParentsComplete($id)
    {
        return $this->getListNameOfPathParents($id, true);
    }

    /**
     * Llama a la función getListNameOfPathParents pasandole un false
     *
     * @author  Jose Maria Casado
     *
     * @param int $id Identificador del nodo que se genera la miga
     *
     * @return array $result Array con los nodos
     */
    public function getListNameOfPathParentsSimple($id)
    {
        return $this->getListNameOfPathParents($id, false);
    }

    /**
    * Devuelve una lista que asociada portales, secciones y nombre de la sección
    * aunque la sección no tenga ningún padre asociado
    *
    * @author Jesús Herranz
    *
    * @param array $sectionData Array que contiene las secciones de un portal.
    * @param string $portalId El id del portal de las secciones.
    *
    * @return $result. Array que contiene las secciones de un portal y además, el nombre asociado a cada sección.
    *
    */
    public function getSectionsName($sectionData, $portalId)
    {
        try {
            if (count($sectionData) > 0) {
                $qb = $this->createQueryBuilder('TreeNode');
                $qb->select('TreeNode.portalId')
                    ->addSelect('TreeNode.sectionId')
                    ->addSelect('TreeNode.nombre')
                    ->where("TreeNode.padreId IS NULL AND TreeNode.portalId = '$portalId'");
                foreach ($sectionData['data'] as $section) {
                    $sectionId = strtolower($section['sectionId']);
                    $qb->orWhere($qb->expr()->andX(
                        $qb->expr()->eq('TreeNode.portalId', "'$portalId'"),
                        $qb->expr()->eq('TreeNode.sectionId', "'$sectionId'")
                    ));
                }
            }
        } catch (\Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        $query = $qb->getQuery();


        $result = $query->getResult();

        return $result;

    }

}