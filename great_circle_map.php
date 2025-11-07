<?php
// great_circle_map.php
// PHP側は都市リストを準備してJSONで渡すだけ

$cities = [
  "Tokyo" => ["lat" => 35.682839, "lon" => 139.759455],
  "Sendai" => ["lat" => 38.268215, "lon" => 140.869356],
  "New York" => ["lat" => 40.712776, "lon" => -74.005974],
  "London" => ["lat" => 51.507351, "lon" => -0.127758],
  "Sydney" => ["lat" => -33.868820, "lon" => 151.209296],
  "San Francisco" => ["lat" => 37.774929, "lon" => -122.419418],

  // 追加分
  "8J1RL (南極昭和基地)" => ["lat" => -69.0096, "lon" => 39.5820],
  "JD1 (小笠原父島)"      => ["lat" => 27.0943, "lon" => 142.1853],
  "8J3EXPO (大阪市夢洲)"  => ["lat" => 34.6489, "lon" => 135.4074],
];

$cities_json = json_encode($cities);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>Fantastic Wave Network : 大圏地図（正距方位図法＋選択＋ズーム＋方位線＋距離円）</title>
<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="https://unpkg.com/topojson-client@3"></script>
<style>
  body { margin:0; overflow:hidden; font-family:sans-serif; }
  svg { width:100vw; height:100vh; display:block; background:#fafafa; }
  .controls {
    position:absolute; top:10px; left:10px;
    background:#fff; border:1px solid #ccc; border-radius:4px;
    padding:6px;
  }
  .controls select, .controls button {
    margin:2px;
    font-size:14px;
  }
  .controls button {
    width:30px; height:30px; font-size:18px; cursor:pointer;
  }
</style>
</head>
<body>
<div class="controls">
  <label for="citySelect">中心都市:</label>
  <select id="citySelect"></select>
  <button id="zoom_in">+</button>
  <button id="zoom_out">-</button>
</div>
<svg id="map"></svg>
<script>
// PHPから受け取った都市データ
const cities = <?php echo $cities_json; ?>;
const width = window.innerWidth;
const height = window.innerHeight;

const svg = d3.select("#map");
const g = svg.append("g");

// セレクトボックスに都市を追加
const select = document.getElementById("citySelect");
Object.keys(cities).forEach(name => {
  const opt = document.createElement("option");
  opt.value = name;
  opt.textContent = name;
  select.appendChild(opt);
});
// デフォルトは東京
select.value = "Tokyo";

// 世界地図データを読み込む
d3.json("https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json").then(world => {
  const countries = topojson.feature(world, world.objects.countries);

  function drawMap(centerCity) {
    g.selectAll("*").remove(); // 前の描画をクリア

    const center = cities[centerCity];

    // 投影法: 正距方位図法
    const projection = d3.geoAzimuthalEquidistant()
        .rotate([-center.lon, -center.lat]) // 中心都市を設定
        .scale(300) // スケール調整（視認性の良いサイズ）
        .translate([width/2, height/2]);

    const path = d3.geoPath(projection);

    // 海岸線
    g.append("path")
     .datum(countries)
     .attr("d", path)
     .attr("fill","#eee")
     .attr("stroke","#333");

    // 方位線（30度ごと）
    const compassRadius = 950;
    for (let angle = 0; angle < 360; angle += 30) {
      const rad = angle * Math.PI / 180;
      const x = width/2 + compassRadius * Math.cos(rad);
      const y = height/2 + compassRadius * Math.sin(rad);
      g.append("line")
       .attr("x1", width/2)
       .attr("y1", height/2)
       .attr("x2", x)
       .attr("y2", y)
       .attr("stroke", "#aaa")
       .attr("stroke-width", 1)
       .attr("stroke-dasharray", "2,2");
    }

    // 距離円（1000kmごと）＋ ラベルは2000kmごと
    const earthRadiusKm = 6371;     // 地球半径
    const maxDistanceKm = 20000;    // 最大距離円まで
    for (let d = 1000; d <= maxDistanceKm; d += 1000) {
      const r = (d / earthRadiusKm) * projection.scale();

      // 円（1000kmごと）
      g.append("circle")
       .attr("cx", width/2)
       .attr("cy", height/2)
       .attr("r", r)
       .attr("stroke", "#aaa")
       .attr("stroke-width", 1)
       .attr("stroke-dasharray", "2,2")
       .attr("fill", "none");

      // ラベルは2000kmごとに表示
      if (d % 2000 === 0) {
        g.append("text")
         .attr("x", width/2 + r + 8)   // 右側外周に少し余白を取って配置
         .attr("y", height/2 + 4)
         .text(`${d} km`)
         .attr("font-size","12px")
         .attr("fill","#666");
      }
    }

    // 各都市マーカー
    Object.entries(cities).forEach(([name, coord]) => {
      const [cx, cy] = projection([coord.lon, coord.lat]);
      g.append("circle")
       .attr("cx", cx)
       .attr("cy", cy)
       .attr("r", name === centerCity ? 6 : 4)
       .attr("fill", name === centerCity ? "red" : "blue");

      g.append("text")
       .attr("x", cx + 8)
       .attr("y", cy + 4)
       .text(name)
       .attr("font-size","12px")
       .attr("fill","#333");
    });
  }

  // 初期描画（東京）
  drawMap("Tokyo");

  // セレクト変更時に再描画
  select.addEventListener("change", e => {
    drawMap(e.target.value);
  });

  // ズーム機能
  const zoom = d3.zoom()
      .scaleExtent([0.1, 20]) // ズーム範囲を広げる
      .on("zoom", (event) => {
        g.attr("transform", event.transform);
      });

  svg.call(zoom);

  // ボタン操作
  d3.select("#zoom_in").on("click", () => {
    svg.transition().call(zoom.scaleBy, 1.2);
  });
  d3.select("#zoom_out").on("click", () => {
    svg.transition().call(zoom.scaleBy, 0.8);
  });
});
</script>
</body>
</html>