<?php
/*
 * Copyright 2020 Máté Bartus
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */

namespace CHItA\TopologicalSort;

use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use LogicException;
use SplFixedArray;

/**
 * Topological sort trait.
 */
trait TopologicalSort
{
    /**
     * Topological sort implementation.
     *
     * The function uses Kahn's algorithm to perform a topological sort. The
     * function returns the topologically sorted array of the provided nodes. It is
     * also possible to perform an action on the sorted elements via a callback
     * function by providing it in the action parameter.
     *
     * By providing a `callable` in the $filter parameter you can filter out nodes
     * in your set.
     *
     * Further more, it is possible to provide the edges of the graph in two ways:
     *   a) By providing an array of edges, containing the each vertex the nth
     *      vertex is connected to.
     *   b) Providing a callback function which returns the list of the vertices.
     *
     * You also have the option to define the directed edges of the graph in a
     * reversed order (specifying the incoming coming edges rather than the
     * outgoing ones).
     *
     * @param iterable $nodes A collection of vertices to sort.
     * @param iterable|callable $edges A collection or callable specifying the
     *                                      edges of the graph.
     * @param bool $flipEdges Whether or not to flip the direction of
     *                                      the edges in the graph.
     * @param callable|null $action A callback to be called, once the node
     *                                      is sorted, or null to return the sorted
     *                                      list.
     * @param callable|null $filter A filter function to skip items in the
     *                                      `$nodes` and `$edges` collections.
     *
     * @return array The topologically sorted `$nodes`.
     *
     * @throws LogicException           When the graph is not acyclic.
     * @throws InvalidArgumentException When `$edges` are nor iterable, nor callable.
     * @throws \Exception               When no Iterator can be extracted from
     *                                  `$edges` and it is not an array or callable.
     */
    public function topologicalSort(
        iterable $nodes,
        $edges,
        bool $flipEdges = false,
        ?callable $action = null,
        ?callable $filter = null
    ): array {
        if (!is_iterable($edges) && !is_callable($edges)) {
            throw new InvalidArgumentException(
                'TopologicalSort(): $edges is neither iterable nor callable.'
            );
        }

        if (is_callable($edges)) {
            $edgeIterator = function ($vertex) use (&$edges) {
                return call_user_func($edges, $vertex);
            };
        } elseif (is_array($edges)) {
            $edgeIterator = function ($vertex) use (&$edges) {
                $val = current($edges);
                next($edges);
                return $val;
            };
        } else {
            if (!($edges instanceof IteratorAggregate)
                && !($edges instanceof Iterator)) {
                throw new InvalidArgumentException(
                    'topologicalSort(): $edges neither an array, callable or Iterator.'
                );
            }

            while (!($edges instanceof Iterator)) {
                $edges = $edges->getIterator();
            }

            $edgeIterator = function ($vertex) use (&$edges) {
                $val = $edges->current();
                $edges->next();
                return $val;
            };
        }

        if ($filter === null) {
            $filter = function ($vertex) {
                return false;
            };
        }

        $incoming_edges = [];
        $outgoing_edges = [];
        foreach ($nodes as $vertex) {
            $edgeSet = $edgeIterator($vertex);
            if (call_user_func($filter, $vertex)) {
                continue;
            }

            $outgoing_edges[$vertex] = $edgeSet;
            if (!array_key_exists($vertex, $incoming_edges)) {
                $incoming_edges[$vertex] = [];
            }

            foreach ($edgeSet as $neighbour) {
                $incoming_edges[$neighbour][] = $vertex;
            }
        }

        if ($flipEdges) {
            return $this->kahnsAlgorithm(
                $outgoing_edges,
                $incoming_edges,
                $action
            );
        }

        return $this->kahnsAlgorithm(
            $incoming_edges,
            $outgoing_edges,
            $action
        );
    }

    /**
     * Kahn's algorithm.
     *
     * The function takes the DAG as two lists of directed edges. The arrays must
     * have the following structure:
     * ```
     * $edgeArray = [
     *  'vertex1' => ['vertexK', ..., 'vertexM'],
     *  ...
     *  'vertexN' => [...]
     * ];
     * ```
     * Where `vertexK` and `vertexM` are the vertices `vertex1` has an edge to or
     * from.
     *
     * @param array $incomingEdges The edge array containing all edges to
     *                                      `vertex1` from `vertexK` and `vertexM`.
     * @param array $outgoingEdges The edge array containing all edges from
     *                                      `vertex1` to `vertexK` and `vertexM`.
     * @param callable|null $action Optional callback function which is
     *                                      called once a node is inserted into the
     *                                      sorted set.
     *
     * @return array The topologically sorted vertices.
     *
     * @throws LogicException When the graph is not acyclic.
     */
    public function kahnsAlgorithm(
        array $incomingEdges,
        array $outgoingEdges,
        ?callable $action = null
    ): array {
        if ($action === null) {
            $action = function ($node) {
            };
        }

        $sorted = new SplFixedArray(count($outgoingEdges));
        $vertices_without_incoming_edges = [];
        foreach ($incomingEdges as $vertex => $edges) {
            if (empty($edges)) {
                $vertices_without_incoming_edges[] = $vertex;
            }
        }

        $i = 0;
        while (!empty($vertices_without_incoming_edges)) {
            $current = array_pop($vertices_without_incoming_edges);
            call_user_func($action, $current);
            $sorted[$i++] = $current;

            foreach ($outgoingEdges[$current] as $vertex) {
                $incomingEdges[$vertex] = array_diff(
                    $incomingEdges[$vertex],
                    [$current]
                );

                if (empty($incomingEdges[$vertex])) {
                    $vertices_without_incoming_edges[] = $vertex;
                }
            }
        }

        if ($sorted->getSize() !== $i) {
            throw new LogicException(
                'KahnsAlgorithm(): The graph contains cycles.'
            );
        }

        return $sorted->toArray();
    }
}
