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
        $h->createEdge($c)->setWeight(103);
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

    /**
     * @expectedException \Mouf\Database\SchemaAnalyzer\MultiDijkstraNoPathException
     */
    public function testDijkstraNoPath() {
        $graph = new Graph();

        $a = $graph->createVertex("a");
        $b = $graph->createVertex("b");
        $c = $graph->createVertex("c");
        $d = $graph->createVertex("d");

        $a->createEdge($b)->setWeight(12);
        $a->createEdge($c)->setWeight(42);

        MultiDijkstra::findShortestPaths($a, $d);
    }

    /**
     * @expectedException \Fhaculty\Graph\Exception\UnexpectedValueException
     */
    public function testDijkstraNegativeWeight() {
        $graph = new Graph();

        $a = $graph->createVertex("a");
        $b = $graph->createVertex("b");

        $a->createEdge($b)->setWeight(-12);

        MultiDijkstra::findShortestPaths($a, $b);
    }

    public function testOptimizedExit() {
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

        $a->createEdge($b)->setWeight(1);
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

        $predecessors = MultiDijkstra::findShortestPaths($a, $b);

        $edges = MultiDijkstra::getCheapestPathFromPredecesArray($a, $b, $predecessors);

        $this->assertCount(1, $edges);
        $this->assertTrue($this->hasVertex($edges[0], $a));
        $this->assertTrue($this->hasVertex($edges[0], $b));
    }

    private function hasVertex(Edge\Base $edge, Vertex $vertex) {
        return $edge->getVerticesStart()->getVertexFirst() === $vertex || $edge->getVerticesTarget()->getVertexFirst() === $vertex;
    }

    public function testDijkstraAmbiguity2() {
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

        $paths = MultiDijkstra::getAllPossiblePathsFromPredecesArray($a, $d, $predecessors);

        $this->assertCount(2, $paths);
        $this->assertCount(2, $paths[0]);
        $this->assertCount(2, $paths[1]);

        $this->assertTrue($this->hasVertex($paths[0][0], $a));
        $this->assertTrue($this->hasVertex($paths[0][0], $b));
        $this->assertTrue($this->hasVertex($paths[0][1], $b));
        $this->assertTrue($this->hasVertex($paths[0][1], $d));

        $this->assertTrue($this->hasVertex($paths[1][0], $a));
        $this->assertTrue($this->hasVertex($paths[1][0], $c));
        $this->assertTrue($this->hasVertex($paths[1][1], $c));
        $this->assertTrue($this->hasVertex($paths[1][1], $d));
    }

    public function testDijkstraAmbiguity3() {
        $graph = new Graph();

        $a = $graph->createVertex("a");
        $b = $graph->createVertex("b");
        $c = $graph->createVertex("c");
        $d = $graph->createVertex("d");
        $e = $graph->createVertex("e");

        $a->createEdge($b)->setWeight(12);
        $a->createEdge($c)->setWeight(42);
        $b->createEdge($d)->setWeight(42);
        $c->createEdge($d)->setWeight(12);
        $d->createEdge($e)->setWeight(1);
        $e->createEdge($d)->setWeight(1);

        $predecessors = MultiDijkstra::findShortestPaths($a, $e);

        $paths = MultiDijkstra::getAllPossiblePathsFromPredecesArray($a, $e, $predecessors);

        $this->assertCount(4, $paths);
    }

}
