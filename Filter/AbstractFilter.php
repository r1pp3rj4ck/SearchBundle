<?php
    namespace Padam87\SearchBundle\Filter;
    
    use Doctrine\ORM\EntityManager;
    use Doctrine\ORM\QueryBuilder;
    
    abstract class AbstractFilter
    {
        protected $_em;
        protected $alias;
        protected $entityName = NULL;
        protected $collectionHandling = array();
        
        public function __construct(EntityManager $em = null)
        {
            $this->setEntityManager($em);
        }
        
        public function setEntityManager(EntityManager $em = null)
        {
            $this->_em = $em;
        }
        
        public function isMultipleLevel()
        {
            return false;
        }
        
        public function createQueryBuilder($entityName, $collectionHandling = array())
        {
            $this->entityName = $entityName;
            $this->collectionHandling = $collectionHandling;
            
            $queryBuilder = $this->_em->getRepository($this->entityName)->createQueryBuilder($this->alias);
            
            return $this->applyToQueryBuilder($queryBuilder);
        }
        
        public function applyToQueryBuilder(QueryBuilder $queryBuilder, $joinName = NULL)
        {            
            if($this->alias == $queryBuilder->getRootAlias()) {
                if($this->toExpr() != false) {
                    $queryBuilder->where($this->toExpr());

                    foreach($this->toParameters() as $parameter) {
                        $queryBuilder->setParameter($parameter['token'], $parameter['value']);
                    }
                }
                
                if($this->entityName != NULL) {
                    $associations = $this->_em->getClassMetadata($this->entityName)->getAssociationNames();

                    foreach($associations as $name) {
                        if($this->_em->getClassMetadata($this->entityName)->isCollectionValuedAssociation($name)) {
                            $factory = new FilterFactory($this->_em);
                            
                            if(isset($this->collectionHandling[$name]) && $this->collectionHandling[$name] == 'AND' && $this->get($name) != null) {
                                foreach($this->get($name) as $k => $filter) {
                                    $queryBuilder = $factory->create($filter, $name . $k)->applyToQueryBuilder($queryBuilder, $name);
                                }
                            }
                            else if ($this->get($name) != null) {
                                $queryBuilder = $factory->create($this->get($name), $name)->applyToQueryBuilder($queryBuilder, $name);
                            }
                        }
                    }
                }
            }
            else if($joinName != NULL) {
                if($this->toExpr() != false) {
                    $queryBuilder->join($queryBuilder->getRootAlias() . '.' . $joinName, $this->alias, 'WITH', $this->toExpr());

                    foreach($this->toParameters() as $parameter) {
                        $queryBuilder->setParameter($parameter['token'], $parameter['value']);
                    }
                }
            }
            
            return $queryBuilder;
        }
        
        abstract public function toArray();
        
        abstract public function toExpr();
        
        abstract public function toParameters();
        
        abstract public function get($field);
    }
?>