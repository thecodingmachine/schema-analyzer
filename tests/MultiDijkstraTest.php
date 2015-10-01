<?php
namespace Mouf\Database\SchemaAnalyzer;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Schema\Schema;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use Fhaculty\Graph\Edge;

class MultiDijkstraTest extends \PHPUnit_Framework_TestCase
{
    public function testDijkstra() {
        $graph = new Graph();

        $a = $graph->createVertex("a");
        $b = $graph->createVertex("b");
        $c = $graph->createVertex("c");
        $d = $graph->createVertex("d");
        $e = $graph->createVertex("e");
        $f = $graph->createVertex("f");
        $g = $graph->createVertex("g");
        $h = $graph->createVertex("h");
        $i = $graph->createVertex("i");
        $j = $graph->createVertex("j");

        $a->createEdge($b)->setWeight(85);
        $a->createEdge($c)->setWeight(217);
        $a->createEdge($e)->setWeight(173);
        $b->createEdge($f)->setWeight(80);
        $c->createEdge($g)->setWeight(186);
        $c->createEdge($h)->setWeight(103);
        $d->createEdge($h)->setWeight(183);
        $e->createEdge($j)->setWeight(502);
        $f->createEdge($i)->setWeight(250);
        $h->createEdge($j)->setWeight(167);
        $i->createEdge($j)->setWeight(84);

        $predecessors = MultiDijkstra::findShortestPaths($a, $j);

        $edges = MultiDijkstra::getCheapestPathFromPredecesArray($a, $j, $predecessors);

        $this->assertTrue($this->hasVertex($edges[0], $a));
        $this->assertTrue($this->hasVertex($edges[0], $c));
        $this->assertTrue($this->hasVertex($edges[1], $c));
        $this->assertTrue($this->hasVertex($edges[1], $h));
        $this->assertTrue($this->hasVertex($edges[2], $j));
        $this->assertTrue($this->hasVertex($edges[2], $h));
    }

    /**
     * @expectedException \Mouf\Database\SchemaAnalyzer\MultiDijkstraAmbiguityException
     */
    public function testDijkstraAmbiguity() {
        $graph = new Graph();

        $a = $graph->createVertex("a");
        $b = $graph->createVertex("b");
        $c = $graph->createVertex("c");
        $d = $graph->createVertex("d");

        $a->createEdge($b)->setWeight(12);
        $a->createEdge($c)->setWeight(42);
        $b->createEdge($d)->setWeight(42);
        $c->createEdge($d)->setWeight(12);

        $predecessors = MultiDijkstra::findShortestPaths($a, $d);

        MultiDijkstra::getCheapestPathFromPredecesArray($a, $d, $predecessors);
    }


    private function hasVertex(Edge\Base $edge, Vertex $vertex) {
        return $edge->getVerticesStart()->getVertexFirst() === $vertex || $edge->getVerticesTarget()->getVertexFirst() === $vertex;
    }
}
