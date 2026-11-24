#!/usr/bin/env python3
"""Reorganizarea catalogului Pet Market: categorie noua, animal (specie) si brand pentru fiecare produs.

Intrare: database/seeders/data/catalog/products-*.json (exportul Store API) si brandurile importate.
Iesire:  database/seeders/data/catalog/taxonomy.json (arborele nou + redirecturi de la categoriile vechi)
         database/seeders/data/catalog/classification.json (wc_id -> categorii, specii, brand, incredere)
Reguli: semnalele explicite din nume au prioritate, apoi categoria veche, apoi descrierea.
Rulare (din radacina proiectului): python3 tools/catalog/classify.py
"""
import collections, html, json, os, re, sys, unicodedata

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
D = os.path.join(ROOT, 'database', 'seeders', 'data', 'catalog')
OVERRIDES = os.path.join(os.path.dirname(__file__), 'overrides.json')


def norm(s):
    s = html.unescape(s or '').replace('\u2019', "'").replace('\u2018', "'")
    s = re.sub(r'<[^>]+>', ' ', s)
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'\s+', ' ', s)


def has(text, *patterns):
    return any(re.search(p, text) for p in patterns)


# ---------------- Arborele nou ----------------
SPECIES = {'caini': 'Câini', 'pisici': 'Pisici'}
PET_SUBS = [
    ('hrana-uscata', 'Hrană uscată'), ('hrana-umeda', 'Hrană umedă'), ('recompense', 'Recompense și gustări'),
    ('diete-veterinare', 'Diete veterinare'), ('suplimente', 'Suplimente și vitamine'), ('antiparazitare', 'Antiparazitare'),
    ('sanatate', 'Sănătate'), ('igiena', 'Igienă și îngrijire'), ('accesorii', 'Accesorii'), ('jucarii', 'Jucării'),
]
CAT_EXTRA = [('nisip-litiera', 'Nisip și litiere')]
OTHER = [('rozatoare', 'Rozătoare și iepuri'), ('pasari', 'Păsări'), ('pesti', 'Pești și acvaristică'), ('reptile', 'Reptile și țestoase'), ('cai', 'Cai'),
         ('animale-ferma', 'Animale de fermă'), ('albine', 'Albine')]
PRO = [('echipamente', 'Echipamente medicale'), ('instrumentar', 'Instrumentar chirurgical'), ('consumabile', 'Consumabile medicale'),
       ('suturi', 'Materiale de sutură'), ('medicamente', 'Medicamente veterinare'), ('vaccinuri', 'Vaccinuri'),
       ('identificare', 'Identificare animale'), ('dezinfectie', 'Biosecuritate și dezinfecție'), ('ddd', 'Dezinsecție și deratizare')]
TREE = [
    {'slug': 'caini', 'name': 'Câini', 'children': PET_SUBS},
    {'slug': 'pisici', 'name': 'Pisici', 'children': PET_SUBS[:8] + CAT_EXTRA + PET_SUBS[8:]},
    {'slug': 'alte-animale', 'name': 'Alte animale', 'children': OTHER},
    {'slug': 'cabinet-veterinar', 'name': 'Cabinet veterinar', 'children': PRO},
]

# ---------------- Semnale ----------------
DOG = [r'\bchien\b', r'\bsimparica', r'\bcain', r'\bcatel', r'\bcatei\b', r'\bcatelus', r'\bdog\b', r'\bdogs\b', r'\bcanine\b', r'\bcanin', r'\bpuppy', r'\bpuppies',
       r'\bhund', r'\bk9\b', r'talie (mica|medie|mare)', r'\bsmall breed', r'\bmedium breed', r'\blarge breed', r'\bgiant breed', r'\bmaxi\b.*\bbreed']
CAT = [r'\bpisic', r'\bcat\b', r'\bcats\b', r'\bfeline\b', r'\bfelin', r'\bkitten', r'\bkatze', r'\bchat\b', r'\bmotan', r'\bpiscic']
RODENT = [r'rozatoare', r'\bchichil', r'^fan\b', r'\bhrana .*iepure\b', r'\bhrana premium iepure', r'pentru iepuri', r'\biepuri\b', r'\brabbit junior', r'\biepure (pitic|premium)', r'\biepur(e|i) (pitic|de companie)', r'\bhamster', r'porcusor de guineea', r'\bcobai', r'\bchinchil', r'\brodent', r'\bdegu\b', r'\bfan pentru',
          r'\bsobolan(i)? de companie']
BIRD = [r'\bpasari\b', r'\baviator', r'\bmei\b', r'\bos de sepie', r'\bcalcar', r'\blololine', r'\bbaton color', r'pentru pasari', r'\bcintez', r'\bzebrut', r'\bcolumbo', r'\bporumbei', r'\bpapagal', r'\bperus', r'\bnimf', r'\bcanar', r'\bbird', r'\bporumbe']
REPTILE = [r'\btestoas', r'\breptil', r'\bbroaste testoase', r'\bterariu', r'\bsopar', r'\bserpi']
FISH = [r'\bacvari', r'\balgacid', r'\bpesti\b', r'\bpesti(sori)? (de acvariu|ornamentali)', r'\bfish food', r'\bacvariu']
HORSE = [r'\bcaba', r'pentru cai\b', r'\bequine', r'\bequin', r'\bhorse', r'\bmanz']
FARM = [r'\bmarcaj', r'\bboli\b', r'\bbolus', r'\bdc\b', r'\blc\b', r'\bforceris', r'\bpurcei', r'\balbenda', r'\blevamisol', r'\blevoplix', r'\bbyemite', r'\bdemotec',
        r'\binele (de )?castrare', r'\bcreion (de )?marcat', r'\bspray (de )?marcare', r'\bmarcare animale', r'\bbovin', r'\bbovi', r'\bruminal', r'\bmamelon', r'\bcopita', r'\bvitel(i)?\b', r'\bjosilac', r'\bcolostr', r'\bvac(a|i)\b', r'\bvitei\b', r'\bvitel(e|i)? (de)? ', r'\bovin', r'\bcaprin', r'\bporc(i|ine)\b', r'\bsuin', r'\bpurcei', r'\bmiei\b',
        r'\bpasari de curte', r'\bgaini', r'\bpui de carne', r'\bbroiler', r'\bmastit', r'\bmetrit', r'\bbujiu', r'\bfuraj', r'\bcheag', r'\brumeg',
        r'\binjectoare intramamar', r'\bintramamar', r'\btamazlic', r'\bferm(a|e)\b', r'\bpoultry', r'\bcattle', r'\bswine', r'\bsheep']
BEE = [r'\balbin', r'\bapicol', r'\bapicultur', r'\bvarroa', r'\bstup']

FOOD_WET = [r'\bhrana umeda', r'\bumed(a|e)\b', r'\bconserv', r'\bplic(uri)?\b', r'\bpouch', r'\bpate\b', r'\bpateu', r'\bcan\b', r'\btin\b',
            r'\bin sos', r'\bgravy', r'\bjelly', r'\baspic', r'\bfillet', r'\bfileuri', r'\bsoup\b', r'\bsupa\b', r'\bloaf', r'\bchunks', r'\bmousse\b',
            r'\bbucati in', r'\bwet\b', r'\bx \d+ ?buc', r'\d+ ?x ?\d+ ?g\b', r'\b\d{2,3} ?g?r?\b(?!.*\bkg)']
FOOD_DRY = [r'\bhrana uscata', r'\buscat(a|e)\b', r'\bdry\b', r'\bcroquet', r'\bgranule', r'\bkibble', r'\b\d+([.,]\d+)? ?kg\b', r'\b\d{3,4} ?g\b']
TREAT = [r'\bveggiedent', r'\bdental chew', r'\brecompens', r'\bviking\b', r'\bcuburi', r'\bloopies', r'\blapte de iac', r'\byak\b', r'\biaurt pasteurizat', r'\byogupet', r'\bbroth\b', r'\bdashi', r'\bchuru\b', r'\bbisque', r'\bbones?\b(?!.*(zgard|lesa|ham))', r'\bos alb', r'\bpiele (presata|de vita|de bivol)', r'\bmaduva', r'\broll din',
         r'\bcalibra joy\b', r'\bjoy (dog|cat)\b', r'\btreat', r'\bsnack', r'\bjerky', r'\bbiscuit', r'\bstick', r'\bbatoan', r'\bdental', r'\bdentastix', r'\bos(ulet)? (natural|presat|alb|din)',
         r'\bcopita (de vita|presata)', r'\burechi de', r'\bchews?\b', r'\bstrips\b', r'\bsandwich', r'\bmeaty coins', r'\bcrunch', r'\bsausage', r'\bsticks\b', r'\bdessert',
         r'\bfriandise', r'\bcookie', r'\btrainer\b', r'\btraining treats', r'\bgoodies\b', r'\bsticksy', r'\bchurro', r'\bcreamy', r'\bsoft treat', r'\bpiele (de|presata)']
DIET_STRONG = [r'\bvet ?solution', r'\bveterinary diet', r'\bprescription diet', r'\bvet ?life', r'\bvd (dog|cat)\b', r'\bcalibra vd\b', r'\bjosera help\b',
               r'\bdieta (veterinara|dietetica)', r'\bhrana dietetica completa', r'\bhpm (cat|dog) (?!adult|junior|senior|kitten|puppy|neutered|with)', r'\btrovet\b',
               r'\b(renal|struvit|oxalat|hepatic|gastrointestinal|gastro intestinal|obesity|obezitate|diabet|recovery|convalescent|dermatology support|digestive support|weight loss|cardiac|urology|kidney)\b']
DIET = [r'\bvet ?solution', r'\bveterinary diet', r'\bprescription diet', r'\bvet ?life', r'\bvd (dog|cat)\b', r'\bcalibra vd\b', r'\bjosera help\b', r'\bdieta (veterinara|dietetica)',
        r'\bhrana dietetica', r'\bdietetic', r'\b(renal|urinary|urinar|struvit|oxalat|hepatic|gastrointestinal|gastro intestinal|hypoallergenic|hipoalergen|obesity|obezitate|diabet|mobility|recovery|convalescent|dermatology support|digestive support|weight loss|cardiac|joint support|urology)\b',
        r'\bhpm (cat|dog) (?!adult|junior|senior|kitten|puppy)', r'\bspecific\b c[a-z]{2}', r'\btrovet\b']
SUPPL = [r'\bviyo\b', r'\bglobifer', r'\bvetaria', r'\bnutricarevet', r'\bvitamin', r'\bvitality\b', r'\bsupliment', r'\bnutraceutic', r'\bcondroprotect', r'\bglucozamin', r'\bomega', r'\bbiotin', r'\bcalciu', r'\bcalcium\b', r'\bprobiotic',
         r'\bprebiotic', r'\bmultivitamin', r'\bpaste? (de|cu) malt', r'\bmalt\b', r'\bcanvit\b', r'\bsynoquin', r'\bcosequin', r'\bflexadin', r'\bdaxocox',
         r'\bsanal\b', r'\bzylkene', r'\bcalm(ex|ing)\b', r'\bsustain\b', r'\bpet phos', r'\bplatinum', r'\bbrewers? yeast', r'\bdrojdie', r'\bulei de somon',
         r'\bsalmon oil', r'\bimmun', r'\bsenior support', r'\bkatalyst', r'\bfortiflora', r'\bproviable', r'\benzime', r'\bhepatoprotect', r'\bsilimarin']
PARASITE = [r'antiparazit', r'\bcestal', r'\bcaniverm', r'\bfypryst', r'\bpip\b', r'\bkiltix', r'\bpudra (insecticida|antiparazitara)', r'\bamflee', r'\bascacid', r'\bvanbendazol', r'\bantipoux', r'\brepelent', r'\bpipet', r'\bspot[ -]?on', r'\bfrontline', r'\bbravecto', r'\bsimparica', r'\bnexgard', r'\bcredelio', r'\bstronghold', r'\badvocate',
            r'\bseresto', r'\bscalibor', r'\bforesto', r'\bmilbemax', r'\bmilprazon', r'\bdrontal', r'\bpraziquantel', r'\bprazicid', r'\bcestem', r'\bendogard',
            r'\bprocox', r'\bbroadline', r'\bprofender', r'\bvectra', r'\badvantix', r'\badvantage', r'\bfiprex', r'\bfipro', r'\beffipro', r'\bpurici', r'\bcapus',
            r'\bdeparazit', r'\bvierm', r'\bzgarda antiparazitara', r'\bzgarda (insecticida|repelenta)', r'\bpraziquantel', r'\bfenbendazol', r'\bpanacur',
            r'\bdronspot', r'\bdehinel', r'\bpratel', r'\babelia', r'\bcaniquantel', r'\bflea', r'\btick', r'\bmilpro', r'\bselehold', r'\bstronghold', r'\bnexgard']
OTC_HEALTH = [r'\botic', r'\bureche', r'\bear\b', r'\bear ', r'\boftalm', r'\bochi', r'\beye\b', r'\bpicaturi', r'\bdermat', r'\bderma\b', r'\bpiele\b',
              r'\bantidiare', r'\bdiaree', r'\bdigesti', r'\bconstipat', r'\bstomat', r'\bdentar', r'\bgingi', r'\bigiena orala', r'\bperiuta de dinti',
              r'\bpasta de dinti', r'\barticul', r'\bcardi', r'\brenal', r'\burinar', r'\bhepat', r'\bficat', r'\brana\b', r'\bplagi', r'\bcicatriz',
              r'\bunguent', r'\bcrema\b', r'\bgel\b', r'\bspray (calmant|cicatrizant|antiseptic)', r'\bantisept', r'\bclorhexidin', r'\bsolutie (otica|oftalmica)',
              r'\bcomportament', r'\banxiet', r'\bstres', r'\bferomon', r'\badaptil', r'\bfeliway', r'\bcalmant', r'\btuse', r'\brespirat']
HYGIENE = [r'\bvopsea', r'\bfur dye', r'\bcolorant', r'\bsampon', r'\bunghii', r'\bspalare labute', r'\bperfume', r'\bpet pad', r'\bped pad', r'\btear stain', r'\blame (de )?tuns', r'\bungere si racire', r'\blama / cutit', r'\boster\b', r'\bshampoo', r'\bbalsam', r'\bconditioner', r'\bspuma (de )?curatare', r'\bmousse (de )?curatare', r'\bcosmetic', r'\bparfum',
           r'\bservetel', r'\bwipes', r'\bscutec', r'\bcovoras(e)? absorbant', r'\btraining pad', r'\bpungi (pentru )?(excremente|caca)', r'\bdeodor',
           r'\bperie\b', r'\bpieptene', r'\bfoarfec(a|e) (de )?unghii', r'\bclesti? (de )?unghii', r'\bfurminator', r'\bmanusa (de )?toaletaj', r'\btoaletaj',
           r'\bmasina de tuns', r'\bigiena', r'\bdouxo\b', r'\ballerderm', r'\bdermoscent']
LITTER = [r'\bnisip(?!.*(recoltare|urigrid|pasari|chinchil|lamaie))', r'\basan\b', r'\blitier', r'\basternut igienic', r'\btofu\b', r'\bsilicat', r'\bclumping', r'\bcat litter', r'\bbentonit']
TOY = [r'\btreat dispenser', r'\bdispenser (de |pentru )?recompense', r'\bflashing', r'\bjucari', r'\btumbler', r'\bmouse\b', r'\bmice\b', r'\blaser', r'\bparty set', r'\bpistol', r'\blick it', r'\bos dur', r'\bgherut', r'\bballs?\b', r'\bdispenser interactiv', r'(?<!& )\btoy\b(?! breed)', r'\btoy\d', r'\bminge', r'\bball\b', r'\bfrisbee', r'\bsoricel', r'\bundita', r'\bpana (de )?jucarie', r'\bfunie',
       r'\bansamblu de joaca', r'\bsisal\b', r'\btunel', r'\blansator', r'\bwobbler',
       r'\bkong\b', r'\bscratch(er)?\b', r'\bzgariat', r'\bteaser']
ACCESS = [r'\btrolley', r'\bscara\b', r'\bplasa (de )?protectie',r'\bcovoras (antiderapant|multifunctional)', r'\bzgard(?!.*(antiparazit|insecticid|repelent|calmant|seresto|scalibor|foresto))', r'\blesa\b', r'\blese\b', r'\bham\b', r'\bhamuri', r'\bbotnit', r'\bbol(uri)?\b', r'\bcastron', r'\bbowl\b', r'\bcusca (de )?transport',
          r'\bgeanta', r'\bpat\b', r'\bpatut', r'\bbed\b', r'\bculcus', r'\bsaltea\b(?!.*(incalzire|chirurg))', r'\bperna\b', r'\bcanapea', r'\bhaine', r'\bhaina\b',
          r'\bpelerina', r'\bjacheta', r'\btricou', r'\bgulere?\b', r'\bcolier( de protectie)?', r'\belisab', r'\btag\b', r'\bmedalion', r'\bdozator', r'\bfantana',
          r'\btavita', r'\bcutie (de )?transport', r'\bcarrier\b', r'\bleash', r'\bcollar\b', r'\bharness', r'\bmuzzle', r'\bvario\b', r'\bhilo\b', r'\bflexi\b',
          r'\bcusca\b(?!.*(inox|internare|contentie|ati))', r'\bcoliva', r'\bterariu', r'\bhranitoare', r'\badapator', r'\bpatura', r'\bblanket']
PRO_EQUIP = [r'\bmonitor', r'\bdefibril', r'\binfuziomat', r'\binjectomat', r'\bconcentrator de oxigen', r'\bmasti? de oxigen', r'\boxigeno', r'\bmasa (inox|de examinare|examinare|auxiliara|portabila|chirurg|de operatie)',
             r'\boperating table', r'\btarga', r'\bchiuveta', r'\bdulap', r'\blamp(a|i) (chirurg|scialitica|ultraviolet|uv|germicid)', r'\buv germicidal', r'\bcentrifug',
             r'\bunitate (mobila|eltro|electro)', r'\belectrocauter', r'\bsigilator', r'\bunit dentar', r'\brefractometr', r'\botoscop', r'\boftalmoscop', r'\bstetoscop',
             r'\becograf', r'\bcantar', r'\bincalzitor', r'\bsistem de incalzire', r'\bsaltea de incalzire', r'\bmodul (custi|internare)', r'\bcusca (inox|contentie|mobila)',
             r'\bstand perfuz', r'\bcarucior', r'\bautoclav', r'\bsterilizator', r'\baparat', r'\blaringoscop', r'\bvaporizator', r'\bpulsoximetr', r'\btermometru',
             r'\bglucometru', r'\bmicroscop', r'\banalizor', r'\bnebulizator', r'\bcompresor', r'\baspirator chirurg', r'\bconcentrator', r'\bpompa (de )?(infuzie|perfuzie)']
PRO_INSTR = [r'\bciocan', r'\btub gastric', r'\belevator', r'\bscaler', r'\bcleste (dentar|pentru)', r'\bcarlig', r'\bcutit special', r'\bdispozitiv metalic', r'\bpens(a|e)\b', r'\bforcep', r'\bfoarfec(a|e)\b(?!.*unghii)', r'\bbisturiu', r'\blama (de )?bisturiu', r'\bport[- ]?ac', r'\bdepartator', r'\bdepresor',
             r'\bsond(a|e)\b', r'\bspecul', r'\bkit instrumentar', r'\bchiureta', r'\bdilatator', r'\bretractor', r'\bclema', r'\bcastrator', r'\bemasculator',
             r'\bpense', r'\bmaner bisturiu', r'\bdental elevator', r'\bextractor', r'\bkocher', r'\bmetzenbaum', r'\bmayo\b', r'\ballis\b', r'\bbackhaus',
             r'\binstrumentar', r'\bnodul', r'\bsonda']
CONS_FIRST = [r'\blam(a|e) (de )?bisturiu', r'\bprelungitor', r'\btub de drenaj', r'\bpenrose', r'\bcombinezon', r'\bsaci (transport )?cadavre', r'\bsonda/tub hranire',
              r'\btub hranire', r'\bbalon anestezie', r'\bsticla bruna', r'\binseminare', r'\bservetele? facial', r'\bleucoplast',r'\btest(e)? rapid', r'\btub endotraheal', r'\blame (de )?microscop', r'\brecoltare', r'\bcamp chirurgical', r'\batela', r'\bac spinal',
              r'\bpunga cpda', r'\btrusa perfuz', r'\bseringa de unica', r'\bmanusi', r'\bbetisor pentru cultura', r'\bmicroperfuzor', r'\bcateter', r'\bconector']
PRO_CONS = [r'\bseringi?\b', r'\bseringa\b', r'\bsiring', r'\bac(e)? (hipodermic|de unica|steril)', r'\bace\b', r'\bneedle', r'\bcatet', r'\bbranul', r'\bmanusi', r'\bgloves',
            r'\bcompres', r'\btifon', r'\bfasa\b', r'\bfese\b', r'\bbandaj', r'\bvata\b', r'\bleucoplast', r'\bplasture', r'\bperfuzor', r'\btrusa perfuz',
            r'\bcamp (operator|steril)', r'\bhalat', r'\bboneta', r'\bmasca chirurg', r'\bspatule', r'\btuburi? (de )?(recoltare|vacutainer)', r'\beprubet',
            r'\bvacutainer', r'\blame (de )?microscop', r'\blamele', r'\bteste? (rapid|snap)', r'\btest\b', r'\bcontainer', r'\bcutie (de )?(colectare|deseuri)',
            r'\bpungi (de )?sterilizare', r'\brola sterilizare', r'\bsteril(e)?\b', r'\bgel ecograf', r'\bgel (pentru )?ecograf', r'\bbanda (adeziva|indicator)',
            r'\bcolier de protectie', r'\bguler (de protectie|elisab)', r'\btub endotraheal', r'\bsonda endotraheal', r'\bmaterial de pansament', r'\bpansament',
            r'\bvet ?wrap', r'\bself[- ]?adhesive', r'\bcoban', r'\bconsumabil']
SUTURE = [r'\bsutur', r'\bcatgut', r'\bsurgicryl', r'\bvicryl', r'\bmonofilament', r'\bbraided\b', r'\busp \d', r'\bfir (de )?sutur', r'\bpolyglactin', r'\bpds\b']
RX = [r'\binj\b', r'\binj\.', r'\binjectabil', r'\bml inj', r'\bantibiotic', r'\banestez', r'\btranchiliz', r'\bsedativ', r'\bketamin', r'\bxylazin', r'\bpropofol',
      r'\bisofluran', r'\bbarbitur', r'\beutanas', r'\bhormon', r'\bprostaglandin', r'\boxitocin', r'\boxytocin', r'\bperfuzabil', r'\bser fiziologic',
      r'\bringer', r'\bglucoza \d', r'\bcorticoid', r'\bdexametazon', r'\bmeloxicam', r'\bcarprofen', r'\bamoxicilin', r'\benrofloxacin', r'\bdoxiciclin',
      r'\btetraciclin', r'\bpenicilin', r'\bgentamicin', r'\bcefalexin', r'\bmetronidazol', r'\bmarbofloxacin', r'\bclindamicin', r'\bsulfamid', r'\btilozin',
      r'\bflorfenicol', r'\boxitetraciclin', r'\bcolistin', r'\bwsp\b', r'\bpulbere orala', r'\bsolutie orala', r'\bdiuretic', r'\bfurosemid', r'\bantiemetic',
      r'\bantiepilep', r'\bfenobarbital', r'\bimepitoin', r'\bvetoryl', r'\btrilostan', r'\bcardalis', r'\bfortekor', r'\bvetmedin', r'\bpimobendan', r'\bisemid',
      r'\bsemintra', r'\bprilactone', r'\bbenazepril', r'\bapoquel', r'\bcytopoint', r'\blibrela', r'\bsolensia', r'\bonsior', r'\bmetacam', r'\bloxicom',
      r'\brimadyl', r'\bcimalgex', r'\bgalliprant', r'\bprevicox', r'\bnorocarp', r'\bcarporal', r'\brycarfa', r'\bmelovem', r'\bdexafort', r'\bcerenia',
      r'\bmaropitant', r'\bsynulox', r'\bkesium', r'\bclavaseptin', r'\bbaytril', r'\benroxil', r'\bmarbocyl', r'\bdoxylin', r'\bronaxan', r'\bconvenia',
      r'\bmetrobactin', r'\bstomorgyl', r'\bantirobe', r'\bclindaseptin', r'\bzodon', r'\bbacteriostat', r'\bmg/ml', r'\b\d+ ?mg x ?\d+', r'\b\d+ ?mg\b.*\b(tbl|tb|tablete|cp|cpr|capsule|caps)\b',
      r'\bx ?\d+ ?(tbl|tb|cpr|cp)\b', r'\btablete? (masticabil|filmate)', r'\bcomprimat']
VACCINE = [r'\bvaccin', r'\bnobivac', r'\bversican', r'\bversiguard', r'\bbiocan', r'\bpurevax', r'\beurican', r'\brabisin', r'\bcanigen', r'\bfelocell',
           r'\bvanguard', r'\bfevaxyn', r'\bleucogen', r'\bparvoruvax', r'\bsalvacan', r'\b\d+ ?dz\b', r'\b\d+ ?doze\b']
IDENT = [r'\bmarcaj', r'\bcarnete? de sanatate', r'\bmicrocip', r'\bmicrochip', r'\bcititor (de )?cip', r'\bcrotali', r'\bpasaport', r'\bidentificare', r'\btatuaj', r'\bear ?tag', r'\ballflex']
DISINF = [r'\bdezinfect', r'\bbiosecurit', r'\bvirocid', r'\bkeno', r'\bcid lines', r'\bbiocid', r'\bsanitiz', r'\bclor\b', r'\beco-chlor', r'\bvirkon',
          r'\btornax', r'\bdetergent', r'\bspalare uger', r'\bdipping', r'\bigienizare', r'\bsuprafete', r'\bdesogerm', r'\bsterillium',
          r'\bperacetic', r'\bformol', r'\bglutaraldehid', r'\bvar cloros', r'\bmaxi-?clean', r'\bsoapy']
PEST = [r'\binsecticid', r'\brodenticid', r'\braticid', r'\bratimor', r'\bmax ?force', r'\bectocid', r'\bk-othrine', r'\bgandac', r'\bmuste\b', r'\bmusca\b',
        r'\btantar', r'\bfurnic', r'\bsoareci', r'\bsobolan', r'\bdeparatiz', r'\bderatiz', r'\bdezinsect', r'\bmomeal', r'\bbait\b', r'\bwax block',
        r'\bprokum', r'\bprodiorat', r'\bbromadiolon', r'\bbrodifacoum', r'\bcipermetrin', r'\bcyper', r'\bdeltametrin', r'\bimidacloprid gel', r'\bflyblock',
        r'\bquick bayt', r'\bagita', r'\bsolfac', r'\bresponsar', r'\bblattanex', r'\bgoliath', r'\bracumin', r'\bbirlane']
MILK = [r'\blapte praf', r'\bmilk replacer', r'\blapte (pentru|praf pentru)? ?(catei|pisoi|pui)', r'\bpuppy milk', r'\bkitten milk', r'\bcat milk', r'\bdog milk',
        r'\blapte\b']

# Linii de hrana: brandurile de hrana (ajuta la diferentierea „hrana” de „medicamente”).
FARM_BRANDS = {'vanelli', 'chevita', 'schils bv', 'interchemie', 'dopharma vet', 'bimeda', 'smi', 'agrocomplex', 'romvac', 'promedivet', 'pharma world'}
FOOD_BRANDS = {'pro science', 'meglium', 'rupp', 'mac\'s', 'brit', 'brit care', 'calibra', 'carnilove', 'josera', 'josera petfood', 'monge', 'gemon', 'mac\'s', "nature's protection",
               "nature's protection superior care", 'taste of the wild', 'leopet', 'lily\'s kitchen', 'joe & pepper', 'bonacibo', 'signature7', 'primordial',
               'natural greatness', 'naturo', 'puffins', 'puffins naturals', 'inaba', 'pet\'s dessert', 'bow wow', 'isegrim', 'vanelli', 'petkult', 'carpathian pet food',
               'royal canin', 'hill\'s', 'hill\'s pet nutrition', 'bwild', 'wise', 'viyo', 'schesir', 'cherie', 'tundra', 'diamond naturals', 'dr. pol', 'chevita',
               'puppies', 'dog food', 'cat food', 'yogupet', 'q pet'}

BRAND_MERGE = {
    'brit care': 'Brit', 'josera petfood': 'Josera', 'elanco animal health': 'Elanco', 'elanco': 'Elanco', "hill's pet nutrition": "Hill's",
    'intervet msd': 'MSD Animal Health', 'msd': 'MSD Animal Health', 'maravet pet': 'Maravet', 'maravet vet': 'Maravet',
    'maravet identificare animale': 'Maravet', "nature's protection superior care": "Nature's Protection", 'puffins naturals': 'Puffins',
    'bayer ah': 'Bayer', 'mitzy': 'Mitzy', 'virbac': 'Virbac', 'zoetis': 'Zoetis', 'ceva': 'Ceva', 'bioiberica': 'Bioiberica', 'artu': 'Artu',
    'aditi': 'Aditi', 'antibiotice iasi': 'Antibiotice Iași',
}
NOT_BRANDS = {'cheag', 'cat food', 'dog food', 'servicii publice'}

OLD_TO_SEGMENT = [  # (fragment din calea categoriei vechi, segment)
    ('fara categorie', None), ('diete', 'diet'), ('dieta', 'diet'), ('prescription diet', 'diet'), ('science plan', 'food'), ('hrana umeda', 'wet'), ('hrana > ', 'food'),
    ('hrana', 'food'), ('pet food', 'food'), ('recompense', 'treat'), ('vitamine', 'suppl'), ('nutraceutice', 'suppl'), ('aminoacizi', 'suppl'),
    ('prebiotice', 'suppl'), ('enzime', 'suppl'), ('hepatoprotectoare', 'suppl'), ('furaje complementare', 'suppl'), ('aditivi', 'suppl'),
    ('antiparazitare', 'parasite'), ('otc', 'health'), ('derma', 'health'), ('solutii otice', 'health'), ('solutii oftalmice', 'health'),
    ('antidiareice', 'health'), ('unguente', 'health'), ('cosmetice', 'hygiene'), ('igiena', 'hygiene'), ('spray', 'health'),
    ('accesorii', 'access'), ('jucarii', 'toy'), ('echipamente veterinare', 'equip'), ('materiale de sutura', 'suture'), ('vaccinuri', 'vaccine'),
    ('antibiotice', 'rx'), ('antiinflamator', 'rx'), ('hormonale', 'rx'), ('tranchilizante', 'rx'), ('barbiturice', 'rx'), ('diuretice', 'rx'),
    ('antiemetice', 'rx'), ('antiepileptice', 'rx'), ('cardiovascular', 'rx'), ('antimicotice', 'rx'), ('medicina interna', 'rx'),
    ('mastite', 'farm'), ('equine', 'horse'), ('apicultura', 'bee'), ('cheag', 'farm'), ('lapte praf', 'milk'),
    ('biosecuritate', 'disinf'), ('dezinfectanti', 'disinf'), ('antiseptice', 'health'), ('insecticide', 'pest'), ('rodenticide', 'pest'),
    ('hrana pasari', 'bird'), ('hrana rozatoare', 'rodent'),
]


def old_paths(p, byid):
    out = []
    for c in p['categories']:
        chain, x = [], byid.get(c['id'])
        while x:
            chain.append(norm(x['name']))
            x = byid.get(x['parent'])
        out.append(' > '.join(reversed(chain)))
    return out


def classify(p, byid, brand_of):
    name = norm(p['name'])
    desc = norm((p.get('short_description') or '') + ' ' + (p.get('description') or ''))[:1500]
    olds = old_paths(p, byid)
    oldj = ' | '.join(olds)
    brand = brand_of(p)
    bnorm = norm(brand or '')
    reasons = []

    # ---- specia ----
    sname = name.replace('royal canin', 'royalcanin').replace("pet's", 'petz').replace('pet s ', 'petz ')
    cat = has(sname, *CAT)
    dog = has(sname, *[d for d in DOG if 'breed' not in d and 'talie' not in d]) or (not cat and has(sname, *DOG))
    # Banda de greutate peste 12 kg („20-40 kg”) inseamna produs pentru caini.
    band = re.search(r'(\d+(?:[.,]\d+)?) ?[-–] ?(\d+(?:[.,]\d+)?) ?kg', name)
    if band and float(band.group(2).replace(',', '.')) > 12 and not cat:
        dog = True
    both = has(name, r'caini (si|&|,) ?pisici', r'pisici (si|&) ?caini', r'dog (and|&) cat', r'cat (and|&) dog', r'caine si pisica', r'pisica si caine', r'dogs? ?& ?cats?',
               r'\bpets\b', r'animale de companie', r'cats? and dogs?')
    if both:
        dog = cat = True
    named = dog or cat
    if has(name, r'\blesa\b', r'\bzgarda\b(?!.*(antiparazit|calmant|seresto|scalibor|kiltix|foresto))', r'\bham\b', r'\bleash') and not has(sname, *CAT):
        dog, cat, named = True, False, True
    if band and float(band.group(2).replace(',', '.')) > 12 and dog and not has(sname, *CAT):
        cat = False
    if not (dog or cat):
        if re.search(r'(> |^)caini|caine|pet food dog|canine', oldj): dog = True
        if re.search(r'(> |^)pisici|pisica|pet food cat|feline', oldj): cat = True
    species_other = None
    for key, pats in ([] if (dog or cat) else [('bee', BEE), ('rodent', [r'rozatoare']), ('reptile', REPTILE), ('fish', FISH), ('bird', BIRD), ('rodent', RODENT), ('horse', HORSE)]):
        if has(sname, *pats) or (key == 'bird' and 'hrana pasari' in oldj) or (key == 'rodent' and 'hrana rozatoare' in oldj) or (key == 'horse' and 'equine' in oldj) or (key == 'bee' and 'apicultura' in oldj):
            species_other = key
            break
    farm = has(name, *FARM) or 'mastite' in oldj or 'cheag' in oldj
    pro_name = has(name, *CONS_FIRST, *PRO_EQUIP, *PRO_INSTR, *PRO_CONS, *SUTURE, *IDENT) and not has(name, *RX)
    if not (dog or cat) and not species_other and not pro_name:
        dsig_dog = has(desc, r'\bcain', r'\bdog'); dsig_cat = has(desc, r'\bpisic', r'\bcats?\b', r'\bfelin')
        if dsig_dog or dsig_cat:
            dog, cat = dsig_dog, dsig_cat
            reasons.append('specie din descriere')

    # ---- segmentul ----
    def seg_from_old():
        real = ' | '.join(o for o in olds if 'fara categorie' not in o)
        for frag, sg in OLD_TO_SEGMENT:
            if sg and frag in real:
                return sg
        return None

    seg = None
    BEHAVIOR = [r'\bcuratare oculara', r'\bessential 6', r'\bpyo kit', r'\batop 7', r'\bbiodent', r'\bhexidin', r'\bwound', r'\bspray pentru rani', r'\bmanuka', r'\botic', r'\bferomon', r'\bcalmdown', r'\bfeliway', r'\badaptil', r'\batopivet', r'\bantistres', r'\banti ?ros', r'\banti ?zgarieturi', r'\beducativ']
    rules = [
        ('toy', [r'\bdispenser interactiv', r'\btreat dispenser']), ('cons', CONS_FIRST), ('health', BEHAVIOR), ('instr', [r'\bfoarfec(a|e) (standard )?pentru fire']), ('suture', SUTURE), ('vaccine', VACCINE), ('ident', IDENT), ('pest', PEST), ('disinf', DISINF), ('equip', PRO_EQUIP),
        ('litter', LITTER), ('milk', MILK), ('parasite', PARASITE), ('diet', DIET_STRONG), ('treat', TREAT), ('toy', TOY),
        ('instr', PRO_INSTR), ('cons', PRO_CONS), ('hygiene', HYGIENE), ('access', ACCESS),
        ('rx', [r'\binj\b', r'\binj\.', r'\binjectabil', r'\bborogluconat', r'\bmg/ml']), ('suppl', SUPPL), ('rx', RX), ('health', OTC_HEALTH),
    ]
    for key, pats in rules:
        if has(name, *pats):
            seg = key
            break
    old_seg = seg_from_old()
    # Categoria veche este mai sigura decat cuvintele generice pentru hrana, diete si medicamente.
    if old_seg in ('vaccine', 'suture', 'pest', 'disinf', 'equip') and seg not in ('diet', 'vaccine', 'suture', 'pest', 'disinf', 'equip', 'cons', 'treat'):
        seg = old_seg
    if seg in (None, 'food') and (dog or cat) and (bnorm in FOOD_BRANDS or has(name, r'\bhrana\b', r'\b(dog|cat) food\b', r'\b\d+([.,]\d+)? ?kg\b')) and not has(name, *SUPPL, *PARASITE):
        seg = seg or 'food'
    if seg in ('health', 'suppl') and (has(name, r'\bhrana\b', r'\b(dog|cat) food\b') or bnorm in FOOD_BRANDS) and (has(name, r'\b\d+([.,]\d+)? ?kg\b') or bnorm == 'pro science'):
        seg = 'food'
    # Antiparazitarele si medicamentele in volume mari (litri, boluri) sunt pentru animale de ferma.
    big = has(name, r'\b\d+([.,]\d+)? ?l\b', r'\bboli\b', r'\bbolus')
    if seg in ('parasite', 'rx') and not named and (big or farm):
        farm = True
        dog = cat = False
    if seg is None and bnorm == 'bio petactive':
        seg = 'suppl'
    if seg is None:
        if has(name, r'\bdispenser', r'\bpungi igienice', r'\brola de curatat', r'\bcovoras', r'\bpungi\b'):
            seg = 'access'
        elif has(name, r'\bapa de colonie', r'\bcolonie', r'\bspray bucal', r'\bpudra (pentru )?ingrijirea'):
            seg = 'hygiene'
        elif (dog or cat) and has(name, r'\bcapsul', r'\btablet', r'\bplicuri', r'\bpasta\b', r'\bpro-kolin', r'\bcystophan', r'\bsynbiotic', r'\bprotectie gastrica', r'\bsprijin'):
            seg = 'suppl'
        elif (dog or cat) and has(name, r'\bspray', r'\bpudra', r'\bsolutie', r'\bwound', r'\brani', r'\bhemostatic'):
            seg = 'health'
    if seg in (None, 'health', 'rx', 'suppl', 'access', 'hygiene', 'cons') and old_seg in ('food', 'wet', 'treat') and (bnorm in FOOD_BRANDS or has(name, r'\bhrana', r'\bfood\b', *FOOD_DRY, *FOOD_WET)):
        seg = 'treat' if old_seg == 'treat' and not name.startswith('hrana') else 'food'
    if seg is None and (bnorm in FOOD_BRANDS or has(name, r'\bhrana\b', r'\bfood\b', r'\badult\b', r'\bjunior\b', r'\bsenior\b', r'\bsterilis', r'\bkitten\b', r'\bpuppy\b')):
        seg = 'food'
    if seg is None and old_seg:
        seg = {'wet': 'food'}.get(old_seg, old_seg)
    if seg is None:
        seg = 'unknown'
    if seg == 'food':
        if (old_seg == 'treat' or has(name, *TREAT)) and not name.startswith('hrana'):
            seg = 'treat'
        elif has(name, *DIET_STRONG):
            seg = 'diet'
    if seg == 'diet' and not has(name, *DIET_STRONG):
        seg = 'food'
    if seg == 'diet' and has(name, r'\bcapsul', r'\btablet', r'\btbl\b', r'\bcps\b', r'\bsupliment', r'\bpicaturi', r'\bsirop'):
        seg = 'suppl'
    if seg == 'pest' and (dog or cat) and has(name, r'\bsampon', r'\bantiparazit', r'\bpipet', r'\bzgard'):
        seg = 'parasite'
    if seg == 'food' and old_seg == 'diet' and not has(name, *DIET_STRONG):
        seg = 'food'

    # Medicamentele pentru animale mari merg la ferma, nu la cabinetul de animale mici.
    if seg == 'rx' and has(name, r'\binj', r'\binjectabil', r'\bclas\b', r'\bforceris', r'\bvetaflumex') and has(name, r'(100|250|500) ?ml\b', r'\b\d+ ?l\b') and not named:
        farm = True
    if seg in ('rx', 'suppl', 'parasite', 'health', 'disinf', 'milk', 'instr', 'cons') and farm and not (dog or cat):
        species_other = species_other or 'farm'
    if not (dog or cat or species_other) and (bnorm in FARM_BRANDS or any(k in oldj for k in ('aditivi', 'lapte praf', 'furaje', 'vitamine per os', 'vitamine injectabile', 'vitamine perfuzabile', 'mastite'))) \
            and seg not in ('disinf', 'pest', 'equip', 'suture', 'vaccine', 'instr', 'cons', 'ident'):
        species_other = 'farm'
    if seg in ('farm', 'horse', 'bee', 'bird', 'rodent'):
        species_other = species_other or seg
        seg = 'other'

    # ---- categoria nouă ----
    cats, conf = [], 'high'
    animals = [s for s, on in (('caini', dog), ('pisici', cat)) if on]
    pet_map = {'food': None, 'diet': 'diete-veterinare', 'treat': 'recompense', 'suppl': 'suplimente', 'parasite': 'antiparazitare',
               'health': 'sanatate', 'hygiene': 'igiena', 'access': 'accesorii', 'toy': 'jucarii', 'litter': 'nisip-litiera', 'milk': 'suplimente'}
    if species_other in ('fish', 'reptile', 'bird', 'rodent') and seg in ('disinf', 'pest', 'rx', 'suppl', 'health', 'cons'):
        seg = 'other'
    if seg in ('equip', 'instr', 'cons', 'suture', 'rx', 'vaccine', 'ident', 'disinf', 'pest'):
        sub = {'equip': 'echipamente', 'instr': 'instrumentar', 'cons': 'consumabile', 'suture': 'suturi', 'rx': 'medicamente', 'vaccine': 'vaccinuri',
               'ident': 'identificare', 'disinf': 'dezinfectie', 'pest': 'ddd'}[seg]
        # Gulerele de protectie, botnitele si unghierele din „Instrumentar” sunt accesorii pentru proprietari.
        cats.append('cabinet-veterinar/' + sub)
        if (farm or species_other == 'farm') and not (dog or cat) and seg in ('cons', 'instr', 'ident', 'rx'):
            cats.append('alte-animale/animale-ferma')
        if seg in ('rx', 'other') and species_other in ('farm', 'horse', 'bee') and (seg == 'rx' or has(name, *RX) or any(k in oldj for k in ('mastite', 'antibiotice', 'hormonale', 'antiinflamator'))):
            cats.append('alte-animale/' + {'farm': 'animale-ferma', 'horse': 'cai', 'bee': 'albine'}[species_other])
    elif species_other and not animals:
        if species_other in ('farm', 'horse') and seg in ('parasite',):
            cats.append('cabinet-veterinar/medicamente')
        if seg == 'other' and species_other in ('farm', 'horse') and (has(name, *RX) or any(k in oldj for k in ('mastite', 'antibiotice', 'hormonale', 'antiinflamator'))):
            cats.append('cabinet-veterinar/medicamente')
        cats.append('alte-animale/' + {'bird': 'pasari', 'rodent': 'rozatoare', 'fish': 'pesti', 'reptile': 'reptile', 'horse': 'cai', 'farm': 'animale-ferma', 'bee': 'albine'}[species_other])
    elif seg == 'unknown':
        conf = 'low'
    else:
        if not animals:
            if seg == 'litter':
                animals = ['pisici']
            elif seg == 'access' and has(name, r'\blesa\b', r'\bzgarda\b', r'\bham\b', r'\bleash', r'\bcollar\b') and not has(name, r'\bpisic', r'\bcat\b'):
                animals = ['caini']; conf = 'medium'
            elif seg == 'treat':
                animals = ['caini']; conf = 'medium'; reasons.append('recompensă fără specie: câini')
            elif bnorm == "pet's dessert" and seg == 'treat':
                animals = ['caini']
            elif seg in ('suppl', 'health', 'hygiene', 'access', 'parasite', 'toy') or farm:
                if farm:
                    cats.append('alte-animale/animale-ferma')
                else:
                    animals = ['caini', 'pisici']; conf = 'medium'; reasons.append('fără specie explicită: câini și pisici')
            else:
                conf = 'low'; reasons.append('specia nu reiese')
        for a in animals:
            sub = pet_map.get(seg)
            if seg == 'food':
                wet = has(name, *FOOD_WET[:-3], r'\bcaserol', r'\bsalam', r'\bvalue pack', r'\bmono protein', r'\b\d+ ?buc\b', r'\btray') or 'umed' in oldj
                dry = has(name, r'\bhrana uscata', r'\buscat', r'\bdry\b', r'\b\d+([.,]\d+)? ?kg\b', r'\bsemiumed') or 'uscat' in oldj
                if wet and not has(name, r'\bhrana uscata', r'\buscat(a|e)\b', r'\bdry\b'):
                    sub = 'hrana-umeda'
                elif dry:
                    sub = 'hrana-uscata'
                elif has(desc, r'\bhrana umeda', r'\bconserv', r'\bpliculet', r'\bpate\b', r'\bin sos') and not has(desc, r'\bhrana uscata', r'\bgranule', r'\bcrochet', r'\bkibble'):
                    sub = 'hrana-umeda'; conf = 'medium' if conf == 'high' else conf
                elif has(desc, r'\bhrana uscata', r'\bgranule', r'\bcrochet', r'\bkibble', r'\bcroquet'):
                    sub = 'hrana-uscata'; conf = 'medium' if conf == 'high' else conf
                elif has(name, r'\b\d{2,3} ?(g|gr)\b') and not has(name, r'\b(300|400|500|700|750|800|900) ?(g|gr)\b'):
                    sub = 'hrana-umeda'; conf = 'medium' if conf == 'high' else conf
                else:
                    sub = 'hrana-uscata'; conf = 'medium' if conf == 'high' else conf; reasons.append('uscată/umedă dedusă din gramaj')
            if sub == 'nisip-litiera' and a == 'caini':
                continue
            cats.append(f'{a}/{sub}')
    if seg in ('instr', 'cons') and has(name, r'\bbotnit', r'\bcolier de protectie', r'\belisab', r'\bguler', r'\bunghii', r'\bclesti', r'\bpieptene',
                                         r'\bperie\b', r'\bmasina de tuns', r'\bfurminator', r'\bzgard', r'\blesa'):
        targets = animals or ['caini', 'pisici']
        extra = 'igiena' if has(name, r'\bunghii', r'\bfoarfec', r'\bclesti', r'\bpieptene', r'\bperie\b', r'\bmasina de tuns', r'\bfurminator') else 'accesorii'
        cats = [f'{a}/{extra}' for a in targets] + cats
    if not cats and has(name, r'\bpasta de carbune'):
        cats, conf = [f'{a}/sanatate' for a in (animals or ['caini', 'pisici'])], 'medium'
    if not cats:
        if has(name, r'\bcarbune activat'):
            cats, conf = ['cabinet-veterinar/medicamente'], 'medium'
        elif 'instrumentar' in oldj:
            cats, conf = ['cabinet-veterinar/consumabile'], 'medium'
        elif 'diverse' in oldj or seg == 'rx':
            cats, conf = ['cabinet-veterinar/medicamente'], 'medium'
        else:
            conf = 'low'

    species = []
    in_cat = lambda prefix: any(c.startswith(prefix) for c in cats)
    if dog or in_cat('caini/'): species.append('Câini')
    if cat or in_cat('pisici/'): species.append('Pisici')
    if in_cat('cabinet-veterinar/') and not in_cat('caini/') and not in_cat('pisici/') and not named and seg not in ('rx', 'vaccine'):
        species = [x for x in species if x not in ('Câini', 'Pisici')]
    if species_other: species.append({'bird': 'Păsări', 'rodent': 'Rozătoare', 'fish': 'Pești', 'reptile': 'Reptile', 'horse': 'Cai', 'farm': 'Animale de fermă', 'bee': 'Albine'}[species_other])
    elif farm and not species: species.append('Animale de fermă')

    return {'categories': list(dict.fromkeys(cats)), 'species': species, 'segment': seg, 'confidence': conf, 'reasons': reasons, 'old': olds, 'brand': brand}


def main():
    products = []
    for f in sorted(os.listdir(D)):
        if f.startswith('products-') and f.endswith('.json'):
            products += json.load(open(os.path.join(D, f)))
    cats = json.load(open(os.path.join(D, 'categories.json')))
    byid = {c['id']: c for c in cats}
    brands = json.load(open(os.path.join(D, 'brands.json')))
    known = sorted({html.unescape(b['name']) for b in brands.get('wp_product_brand', [])} | {html.unescape(b['name']) for b in brands.get('_from_local_attribute_Brand', [])},
                   key=lambda s: -len(s))

    def canonical(b):
        if not b:
            return None
        n = norm(b)
        if n in NOT_BRANDS:
            return None
        return BRAND_MERGE.get(n, b.strip())

    def brand_of(p):
        for b in p.get('brands') or []:
            return canonical(html.unescape(b['name']))
        for a in p.get('attributes') or []:
            if norm(a.get('name')) == 'brand' and a.get('terms'):
                return canonical(html.unescape(a['terms'][0]['name']))
        n = norm(p['name'])
        for b in known:
            nb = norm(b)
            if nb not in NOT_BRANDS and len(nb) > 2 and re.search(r'(^|\W)' + re.escape(nb) + r'(\W|$)', n):
                return canonical(b)
        return None

    overrides = json.load(open(OVERRIDES)) if os.path.exists(OVERRIDES) else {}
    result = {}
    for p in products:
        r = classify(p, byid, brand_of)
        o = overrides.get(str(p['id']))
        if o:
            r.update({k: v for k, v in o.items() if k in ('categories', 'species', 'brand')})
            r['confidence'] = 'manual'
        r['name'] = html.unescape(p['name'])
        result[str(p['id'])] = r

    # Redirecturi: fiecare categorie veche duce la categoria noua in care au ajuns cele mai multe produse ale ei.
    votes = collections.defaultdict(collections.Counter)
    for p in products:
        for c in p['categories']:
            # Votul urca si la toti parintii: o categorie veche fara produse directe ia destinatia copiilor ei.
            x = byid.get(c['id'])
            while x:
                for nc in result[str(p['id'])]['categories'][:1]:
                    votes[x['id']][nc] += 1
                x = byid.get(x['parent'])
    redirects = {}
    for c in cats:
        chain, x = [], c
        while x:
            chain.append(x['slug']); x = byid.get(x['parent'])
        old = '/categorie-produs/' + '/'.join(reversed(chain)) + '/'
        v = votes[c['id']]
        if v:
            total = sum(v.values())
            t, n = v.most_common(1)[0]
            # Categoria veche era larga (produsele ei s-au impartit in mai multe subcategorii noi):
            # redirectul merge la sectiunea dominanta (Caini, Pisici...), nu la o subcategorie la intamplare.
            if n / total < 0.6:
                t = collections.Counter(k.split('/')[0] for k in v.elements()).most_common(1)[0][0]
            redirects[old] = '/categorie-produs/' + t + '/'
        else:
            redirects[old] = '/magazin/'

    tree = [{'slug': t['slug'], 'name': t['name'], 'children': [{'slug': s, 'name': n} for s, n in t['children']]} for t in TREE]
    json.dump({'tree': tree, 'redirects': redirects, 'brand_merge': BRAND_MERGE, 'not_brands': sorted(NOT_BRANDS)},
              open(os.path.join(D, 'taxonomy.json'), 'w'), ensure_ascii=False, indent=1)
    json.dump({k: {kk: v[kk] for kk in ('categories', 'species', 'brand', 'confidence')} for k, v in result.items()},
              open(os.path.join(D, 'classification.json'), 'w'), ensure_ascii=False)

    # Rezumat
    c = collections.Counter(); conf = collections.Counter(v['confidence'] for v in result.values())
    for v in result.values():
        for k in v['categories']:
            c[k] += 1
    if '--quiet' not in sys.argv:
        for k, n in sorted(c.items()):
            print(f'{n:5}  {k}')
        print('fara categorie:', sum(1 for v in result.values() if not v['categories']), '| incredere:', dict(conf),
              '| fara brand:', sum(1 for v in result.values() if not v['brand']))
    json.dump(result, open(os.path.join(os.environ.get('CLASSIFY_DEBUG_DIR', '/tmp'), 'classification-debug.json'), 'w'), ensure_ascii=False)


if __name__ == '__main__':
    main()
