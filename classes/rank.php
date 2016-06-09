<?
    abstract class rank
    {
        function update($id)
        {
            /* Flush old results */
            vsql::delete("achievements", $id, "ground");

            /* Get all achievements */
            $rank_data = vsql::get("SELECT start, options, categories FROM grounds WHERE deleted = 0 AND id = " . vsql::quote($id));
            $cats = $rank_data["categories"];

            /* Get results */
            $results =
                vsql::retr("SELECT a.id, g.start, g.type, a.position, a.user, a.points, a.style, a.duration, a.place
                            FROM achievements AS a
                            JOIN grounds AS g ON a.ground = g.id AND g.deleted = 0
                            WHERE a.deleted = 0 AND " . vsql::id_condition($cats, "a.categ"));

            /* Compute */
            foreach($this->compute($results) as $item)
                vsql::insert("achievements",
                    array_merge($item, array("ground" => $id, "date" => $rank_data["start"])));

            /* We're done! */
        }

        function scores($id)
        {
            $scores = vsql::retr("SELECT
                            a.id, g.start, a.position, a.user, a.points, g.city, u.ref AS user_name,
                            g.name AS event_name, g.start AS event_date, g.city AS event_city, g.id AS event_id
                            FROM grounds AS rank
                            JOIN achievements AS a ON a.date >= rank.start AND a.date <= rank.finish AND a.deleted = 0
                              AND CONCAT(',', rank.categories, ',') LIKE CONCAT('%,', a.categ, ',%')
                            JOIN grounds AS g ON a.ground = g.id AND g.deleted = 0
                            JOIN users AS u ON u.id = a.user
                            WHERE rank.id = " . vsql::quote($id) . " ORDER BY g.start");

            return $scores;
        }

        function compute($results)
        {
            $out = array();
            foreach($results as $i)
            {
                $us = $i["user"];
                if(!isset($out[$us]))
                    $out[$us] = array_merge($i, array("points" => 0, "scores" => array()));

                $out[$us]["scores"][$i["event_id"]] = $i;
                $out[$us]["points"] += $i["points"];
            }


            usort($out, function ($a, $b) {
                if(($ptdiff = $b["points"] - $a["points"]))
                    return $ptdiff;

                /* Taka sama liczba punktów - nie-do-brze */

                /* Zobacz zawody, w których zawodnicy ze sobą rywalizowali */
                $wszystkie_eventy = array_unique(array_merge(array_keys($a["scores"]), array_keys($b["scores"])));
                $lepsze_miejsca = 0;
                foreach($wszystkie_eventy as $event_id)
                {
                    /* I jak? */
                    if(!isset($a["scores"][$event_id])) continue;
                    if(!isset($b["scores"][$event_id])) continue;

                    /* Zatem - rywalizowali */
                    if($b["scores"][$event_id]["position"] > $a["scores"][$event_id]["position"])
                        $lepsze_miejsca++;
                    else if($b["scores"][$event_id]["position"] < $a["scores"][$event_id]["position"])
                        $lepsze_miejsca--;
                }

                if($lepsze_miejsca) return $lepsze_miejsca;

                /* Ile mieli pierwszych miejsc? */
                for($i = 1; $i<=100; $i++)
                {
                    $wiecej_miejsc = 0;
                    foreach($a["scores"] as $eid => $score)
                        if($score["position"] == $i) $wiecej_miejsc--;
                    foreach($b["scores"] as $eid => $score)
                        if($score["position"] == $i) $wiecej_miejsc++;
                    if($wiecej_miejsc)
                        return $wiecej_miejsc;
                }
                return 0;
            });

            $points = -1; $pos = 0; $rank = array();
            foreach($out as $k => $i)
            {
                $pos ++;
                $points = $i["points"];
                if($points > 0)
                    $rank[] = array(
                        "user" => $i["user"],
                        "points" => $points,
                        "position" => $pos,
                        "scores" => $i["scores"],
                        "user_name" => $i["user_name"],
                    );
            }

            return $rank;
        }

        function events($rank)
        {
            $events = array();
            foreach($rank as $i)
                foreach($i["scores"] as $j)
                    $events[$j["event_id"]] = array_intersect_key($j, array_flip(array("event_name", "event_date", "event_city")));

            uasort($events, function ($a, $b) {
                if(($datediff = strcmp($b["event_date"], $a["event_date"]))) return $datediff;
                return strcmp($b["event_name"], $a["event_name"]);
            });

            return $events;
        }
    }
