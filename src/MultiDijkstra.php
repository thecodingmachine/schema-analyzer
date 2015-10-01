<?php

namespace Mouf\Database\SchemaAnalyzer;

use Fhaculty\Graph\Edge;
use Fhaculty\Graph\Exception\UnexpectedValueException;
use Fhaculty\Graph\Vertex;
use \SplPriorityQueue;

/**
 * Dijkstra's shortest path algorithm modified to measure all possible shortest paths.
 */
class MultiDijkstra
{
    /**
     * Get all edges on shortest path for this vertex
     *
     * @throws UnexpectedValueException when encountering an Edge with negative weight
     * @return array<string, Vertex[]> where key is the destination vertex name and value is an array of possible origin vertex
     */
    public static function findShortestPaths(Vertex $startVertex, Vertex $endVertex)
    {
        $totalCostOfCheapestPathTo  = [];
        // start node distance
        $totalCostOfCheapestPathTo[$startVertex->getId()] = INF;

        $endVertexId = $endVertex->getId();

        // just to get the cheapest vertex in the correct order
        $cheapestVertex = new SplPriorityQueue();
        $cheapestVertex->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $cheapestVertex->insert($startVertex, 0);

        // predecessors
        $predecesEdgeOfCheapestPathTo  = [];
        //$predecesVertexOfCheapestPathTo[$startVertex->getId()] = [ $startVertex ];

        // mark vertices when their cheapest path has been found
        $usedVertices  = [];

        $isFirst = true;

        // Repeat until all vertices have been marked
        $totalCountOfVertices = count($startVertex->getGraph()->getVertices());
        for ($i = 0; $i < $totalCountOfVertices; ++$i) {
            $currentVertex = NULL;
            $currentVertexId = NULL;
            $isEmpty = false;
            do {
                // if the priority queue is empty there are isolated vertices, but the algorithm visited all other vertices
                if ($cheapestVertex->isEmpty()) {
                    $isEmpty = true;
                    break;
                }
                // Get cheapest unmarked vertex
                $cheapestResult = $cheapestVertex->extract();
                $currentVertex = $cheapestResult['data'];
                $currentCost = $cheapestResult['priority'];
                $currentVertexId = $currentVertex->getId();
            // Vertices can be in the priority queue multiple times, with different path costs (if vertex is already marked, this is an old unvalid entry)
            } while (isset($usedVertices[$currentVertexId]));

            // Check premature end condition
            // If the end vertex is marked as done and the next lowest possible weight is bigger than end vertix,
            // we are done processing.
            if (isset($usedVertices[$endVertexId]) && $totalCostOfCheapestPathTo[$endVertexId] < $currentCost) {
                break;
            }

            // catch "algorithm ends" condition
            if ($isEmpty) {
                break;
            }

            if ($isFirst) {
                $isFirst = false;
            } else {
                // mark this vertex
                $usedVertices[$currentVertexId] = true;
            }

            // check for all edges of current vertex if there is a cheaper path (or IN OTHER WORDS: Add reachable nodes from currently added node and refresh the current possible distances)
            foreach ($currentVertex->getEdgesOut() as $edge) {
                $weight = $edge->getWeight();
                if ($weight < 0) {
                    throw new UnexpectedValueException('Djkstra not supported for negative weights - Consider using MooreBellmanFord');
                }

                $targetVertex = $edge->getVertexToFrom($currentVertex);
                $targetVertexId = $targetVertex->getId();

                // if the targetVertex is marked, the cheapest path for this vertex has already been found (no negative edges) {
                if (!isset($usedVertices[$targetVertexId])) {
                    // calculate new cost to vertex
                    $newCostsToTargetVertex = $totalCostOfCheapestPathTo[$currentVertexId] + $weight;
                    if (is_infinite($newCostsToTargetVertex)) {
                        $newCostsToTargetVertex = $weight;
                    }

                    if ((!isset($predecesEdgeOfCheapestPathTo[$targetVertexId]))
                           // is the new path cheaper?
                           || $totalCostOfCheapestPathTo[$targetVertexId] > $newCostsToTargetVertex){

                        // Not an update, just a new insert with lower cost
                        $cheapestVertex->insert($targetVertex, - $newCostsToTargetVertex);
                        // so the lowest cost will be extracted first
                        // and higher cost will be skipped during extraction

                        // update/set costs found with the new connection
                        $totalCostOfCheapestPathTo[$targetVertexId] = $newCostsToTargetVertex;
                        // update/set predecessor vertex from the new connection
                        $predecesEdgeOfCheapestPathTo[$targetVertexId] = [ $edge ];
                    } elseif ($totalCostOfCheapestPathTo[$targetVertexId] == $newCostsToTargetVertex) {
                        // Same length paths. We need to add the predecessor to the list of possible predecessors.
                        $predecesEdgeOfCheapestPathTo[$targetVertexId][] = $edge;
                    }
                }

            }
        }

        /*if ($totalCostOfCheapestPathTo[$startVertex->getId()] === INF) {
            unset($predecesVertexOfCheapestPathTo[$startVertex->getId()]);
        }*/

        // algorithm is done, return resulting edges
        return $predecesEdgeOfCheapestPathTo;
    }

    /**
     * @param array<string, Vertex[]> $predecesEdgesArray  key is the destination vertex name and value is an array of possible origin vertex
     * @return Edge\Base[]
     */
    public static function getCheapestPathFromPredecesArray(Vertex $startVertex, Vertex $endVertex, array $predecesEdgesArray) {
        $edges = [];
        $currentVertex = $endVertex;
        while ($currentVertex !== $startVertex) {
            $predecessorEdges = $predecesEdgesArray[$currentVertex->getId()];
            if (count($predecessorEdges) > 1) {
                throw new MultiDijkstraAmbiguityException("There are many possible shortest paths to link vertex '".$startVertex->getId()."' to '".$endVertex->getId()."'");
            }
            /* @var $edge \Fhaculty\Graph\Edge\Base */
            $edge = $predecessorEdges[0];
            $edges[] = $edge;
            if ($currentVertex === $edge->getVerticesStart()->getVertexFirst()) {
                $currentVertex = $edge->getVerticesTarget()->getVertexFirst();
            } else {
                $currentVertex = $edge->getVerticesStart()->getVertexFirst();
            }
        }

        return array_reverse($edges);
    }
}
