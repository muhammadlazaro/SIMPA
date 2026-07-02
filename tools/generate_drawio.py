import xml.etree.ElementTree as ET
from xml.dom import minidom

def create_drawio():
    mxfile = ET.Element("mxfile")
    diagram = ET.SubElement(mxfile, "diagram", id="d1", name="Page-1")
    mxGraphModel = ET.SubElement(diagram, "mxGraphModel", dx="1000", dy="1000", grid="1", gridSize="10", guides="1", tooltips="1", connect="1", arrows="1", fold="1", page="1", pageScale="1", pageWidth="1169", pageHeight="1654", math="0", shadow="0")
    root = ET.SubElement(mxGraphModel, "root")
    
    ET.SubElement(root, "mxCell", id="0")
    ET.SubElement(root, "mxCell", id="1", parent="0")
    
    swimlanes = [
        "Unit Kerja\n(Pemilik Aplikasi)", 
        "Tim Pengelola\nAplikasi", 
        "Tim Analis\nDesain", 
        "Tim Implementasi\nAplikasi", 
        "Tim Uji\nKeamanan", 
        "Tim DevOps"
    ]
    
    lane_width = 160
    total_width = lane_width * len(swimlanes)
    pool_h = 2700
    
    pool = ET.SubElement(root, "mxCell", id="pool", value="SIMPA", style="swimlane;childLayout=stackLayout;resizeParent=1;resizeParentMax=0;horizontal=1;startSize=30;horizontalStack=1;html=1;fontStyle=1;fontSize=14;fillColor=#f5f5f5;fontColor=#333333;strokeColor=#666666;", vertex="1", parent="1")
    ET.SubElement(pool, "mxGeometry", x="0", y="0", width=str(total_width), height=str(pool_h), **{"as": "geometry"})
    
    for i, name in enumerate(swimlanes):
        lane = ET.SubElement(root, "mxCell", id=f"lane_{i+1}", value=name, style="swimlane;startSize=40;horizontal=1;html=1;fontStyle=1;fontSize=12;fillColor=#ffffff;fontColor=#333333;strokeColor=#666666;", vertex="1", parent="pool")
        ET.SubElement(lane, "mxGeometry", x=str(i * lane_width), y="30", width=str(lane_width), height=str(pool_h - 30), **{"as": "geometry"})
        
    nodes = [
        {"id": "Start", "text": "", "type": "start", "L": 1, "y": 60, "w": 40, "h": 40},
        {"id": "U1", "text": "Pengajuan Aplikasi\nbaru", "type": "activity", "L": 1, "y": 140, "w": 130, "h": 60},
        {"id": "U2", "text": "Isi data aplikasi\n+ Upload formulir\npendukung", "type": "activity", "L": 1, "y": 240, "w": 130, "h": 60},
        
        {"id": "P1", "text": "Verifikasi\nPengajuan", "type": "activity", "L": 2, "y": 340, "w": 130, "h": 60},
        {"id": "P2", "text": "Studi Kelayakan", "type": "activity", "L": 2, "y": 440, "w": 130, "h": 60},
        {"id": "D1", "text": "Layak", "type": "decision", "L": 2, "y": 540, "w": 100, "h": 60},
        
        {"id": "A1", "text": "Melakukan Analisa\nDesain", "type": "activity", "L": 3, "y": 660, "w": 130, "h": 60},
        {"id": "A2", "text": "Mengupload\nLaporan Analisa\nDesain", "type": "activity", "L": 3, "y": 760, "w": 130, "h": 60},
        
        {"id": "I1", "text": "Pengembangan\nAplikasi", "type": "activity", "L": 4, "y": 860, "w": 130, "h": 60},
        {"id": "I2", "text": "Build selesai", "type": "activity", "L": 4, "y": 960, "w": 130, "h": 60},
        {"id": "I3", "text": "Mengunggah\nTemplate UAT &\nPetunjuk Aplikasi\nke sistem", "type": "activity", "L": 4, "y": 1060, "w": 140, "h": 70},
        
        {"id": "U3", "text": "Mengunduh dan\nMengisi Form UAT", "type": "activity", "L": 1, "y": 1180, "w": 130, "h": 60},
        {"id": "U4", "text": "Mengunggah Hasil\nUAT ke sistem", "type": "activity", "L": 1, "y": 1280, "w": 130, "h": 60},
        
        {"id": "P3", "text": "Memverifikasi Hasil\nUAT", "type": "activity", "L": 2, "y": 1380, "w": 130, "h": 60},
        {"id": "P4", "text": "Aplikasi siap diuji\nkeamanan", "type": "activity", "L": 2, "y": 1480, "w": 130, "h": 60},
        
        {"id": "K1", "text": "Terima aplikasi\nuntuk diuji", "type": "activity", "L": 5, "y": 1580, "w": 130, "h": 60},
        {"id": "K2", "text": "Lakukan Uji\nKeamanan Aplikasi", "type": "activity", "L": 5, "y": 1680, "w": 130, "h": 60},
        {"id": "D2", "text": "Lolos Uji", "type": "decision", "L": 5, "y": 1780, "w": 100, "h": 60},
        
        {"id": "K3", "text": "Buat catatan\nperbaikan", "type": "activity", "L": 5, "y": 1880, "w": 130, "h": 60},
        {"id": "K4", "text": "Kirim catatan ke\npengelola aplikasi", "type": "activity", "L": 5, "y": 1980, "w": 130, "h": 60},
        
        {"id": "P5", "text": "Tindak Lanjut\nPerbaikan", "type": "activity", "L": 2, "y": 1980, "w": 130, "h": 60},
        
        {"id": "K5", "text": "Kirim\npemberitahuan\nlolos uji ke sistem", "type": "activity", "L": 5, "y": 2100, "w": 130, "h": 60},
        
        {"id": "P6", "text": "Terima hasil\npengujian\nkeamanan", "type": "activity", "L": 2, "y": 2200, "w": 130, "h": 60},
        {"id": "P7", "text": "Mengupload berita\nacara serah terima\ndan rilis", "type": "activity", "L": 2, "y": 2300, "w": 140, "h": 60},
        
        {"id": "U5", "text": "Mengunduh &\nMengupload BA\nSerah terima (TTE)", "type": "activity", "L": 1, "y": 2400, "w": 140, "h": 60},
        
        {"id": "O1", "text": "Melakukan\nPenempatan Aplikasi\ndi Lingkungan\nProduksi", "type": "activity", "L": 6, "y": 2500, "w": 140, "h": 70},
        {"id": "End", "text": "", "type": "end", "L": 6, "y": 2620, "w": 40, "h": 40},
    ]
    
    for n in nodes:
        cx = (n['L'] - 1) * lane_width + (lane_width / 2)
        x = cx - (n['w'] / 2)
        y = n['y']
        
        style = ""
        if n['type'] == 'start':
            style = "ellipse;whiteSpace=wrap;html=1;fillColor=#333333;strokeColor=#333333;"
        elif n['type'] == 'end':
            style = "ellipse;shape=doubleEllipse;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#333333;strokeWidth=3;"
        elif n['type'] == 'decision':
            style = "rhombus;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#333333;fontColor=#333333;fontStyle=1;strokeWidth=1.5;"
        else:
            style = "rounded=1;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#333333;fontColor=#333333;arcSize=20;strokeWidth=1.5;"
            
        cell = ET.SubElement(root, "mxCell", id=n['id'], value=n['text'], style=style, vertex="1", parent="1")
        ET.SubElement(cell, "mxGeometry", x=str(x), y=str(y), width=str(n['w']), height=str(n['h']), **{"as": "geometry"})

    edges = [
        {"src": "Start", "dst": "U1", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "U1", "dst": "U2", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        
        {"src": "U2", "dst": "P1", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "P1", "dst": "P2", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "P2", "dst": "D1", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        
        {"src": "D1", "dst": "A1", "exit": (0.5, 1), "entry": (0.5, 0), "label": "Ya"},
        
        {"src": "A1", "dst": "A2", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        
        {"src": "A2", "dst": "I1", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "I1", "dst": "I2", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "I2", "dst": "I3", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        
        {"src": "I3", "dst": "U3", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "U3", "dst": "U4", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        
        {"src": "U4", "dst": "P3", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "P3", "dst": "P4", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        
        {"src": "P4", "dst": "K1", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "K1", "dst": "K2", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "K2", "dst": "D2", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        
        {"src": "D2", "dst": "K3", "exit": (0, 0.5), "entry": (0.5, 0), "label": "Tidak"},
        {"src": "K3", "dst": "K4", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "K4", "dst": "P5", "exit": (0, 0.5), "entry": (1, 0.5), "label": ""},
        
        {"src": "P5", "dst": "I1", "exit": (0.5, 0), "entry": (0, 0.5), "label": ""},
        
        {"src": "D2", "dst": "K5", "exit": (1, 0.5), "entry": (0.5, 0), "label": "Ya"},
        
        {"src": "K5", "dst": "P6", "exit": (0, 0.5), "entry": (0.5, 0), "label": ""},
        {"src": "P6", "dst": "P7", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        
        {"src": "P7", "dst": "U5", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        
        {"src": "U5", "dst": "O1", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
        {"src": "O1", "dst": "End", "exit": (0.5, 1), "entry": (0.5, 0), "label": ""},
    ]
    
    for i, e in enumerate(edges):
        style = (
            "edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;"
            "strokeColor=#333333;fontColor=#333333;strokeWidth=1.5;"
            f"exitX={e['exit'][0]};exitY={e['exit'][1]};exitDx=0;exitDy=0;"
            f"entryX={e['entry'][0]};entryY={e['entry'][1]};entryDx=0;entryDy=0;"
        )
        edge = ET.SubElement(root, "mxCell", id=f"edge_{i}", value=e['label'], style=style, edge="1", parent="1", source=e['src'], target=e['dst'])
        geom = ET.SubElement(edge, "mxGeometry", relative="1", **{"as": "geometry"})
        if e['label']:
            lbl = ET.SubElement(edge, "mxCell", id=f"edge_lbl_{i}", value=e['label'], style="edgeLabel;html=1;align=center;verticalAlign=middle;resizable=0;points=[];fontStyle=1;labelBackgroundColor=#ffffff;", vertex="1", connectable="0", parent=f"edge_{i}")
            ET.SubElement(lbl, "mxGeometry", x="-0.3", y="0", relative="1", **{"as": "geometry"})

    xmlstr = minidom.parseString(ET.tostring(mxfile)).toprettyxml(indent="  ")
    with open('docs/Activity_Diagram_SIMPA_Final.drawio', 'w', encoding='utf-8') as f:
        f.write(xmlstr)

create_drawio()
