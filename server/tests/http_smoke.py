"""End-to-end tests against an isolated local PHP process and disposable database."""
import zlib, struct
import os, sys, tempfile, subprocess, time, urllib.request, urllib.parse, urllib.error, http.cookiejar, re, pathlib, sqlite3
root=pathlib.Path(__file__).resolve().parents[1]
php=sys.argv[1] if len(sys.argv)>1 else 'php'
with tempfile.TemporaryDirectory(prefix='kita-http-') as tmp:
    env=dict(os.environ,REUNION_TEST_DSN='sqlite:'+tmp+'/test.sqlite',REUNION_TEST_STORAGE=tmp+'/private')
    subprocess.run([php,str(root/'scripts/manage.php'),'seed'],env=env,check=True,capture_output=True)
    srv=subprocess.Popen([php,'-S','127.0.0.1:8791','-t',str(root/'public'),str(root/'public/router.php')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    base='http://127.0.0.1:8791/?page='
    n=0
    def check(ok,label):
        global n
        assert ok,label
        n+=1;print('PASS',n,label)
    def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(c,page,data=None,files=None):
        headers={}
        if files:
            boundary='KitaReunionTestBoundary';parts=[]
            for k,v in data.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
            for k,(name,mime,content) in files.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+b'\r\n')
            body=b''.join(parts)+f'--{boundary}--\r\n'.encode();headers['Content-Type']='multipart/form-data; boundary='+boundary
        else:body=urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            r=c.open(urllib.request.Request(base+page,data=body,headers=headers));return r.status,r.read()
        except urllib.error.HTTPError as e:return e.code,e.read()
    def token(c,page):
        status,b=request(c,page);m=re.search(rb'name="csrf" value="([a-f0-9]+)"',b);assert m,(status,b[:500]);return m[1].decode()
    def post(c,page,action,values={},files=None):return request(c,page,{'csrf':token(c,page),'action':action,**values},files)
    def login(i):
        c=client();status,b=post(c,'login','login',{'email':f'sample{i}@example.test','password':'Sample1987!'});assert status==200,(status,b[:100]);return c
    try:
        for _ in range(50):
            try:request(client(),'home');break
            except urllib.error.URLError:time.sleep(.1)
        anon=client();s,b=request(anon,'admin');check(s==403,'未ログインの管理画面は拒否')
        participant=login(3);s,b=request(participant,'my');check(b'K87-00003' in b and b'K87-00001' not in b,'本人の申込だけを表示')
        s,b=request(participant,'admin_roles');check(s==403,'一般参加者の幹事権限画面を拒否')
        s,b=request(participant,'my',{'action':'cancel','reason':'test','csrf':'forged'});check(s==400,'CSRF不一致の更新を拒否')
        owner=login(1);organizer=login(2)
        values={'date':'2026-10-29','category':'印刷費','payee':'架空店','purpose':'資料','amount':'1000','reason':'操作検証'}
        receipt=b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n'
        s,b=post(organizer,'admin_accounting','expense',values,{'receipt':('sample.pdf','application/pdf',receipt)});check(s==200,'経費と領収書を同時保存')
        con=sqlite3.connect(tmp+'/test.sqlite');eid=con.execute('select max(id) from expenses').fetchone()[0];fid=con.execute('select max(id) from private_files').fetchone()[0]
        s,b=request(participant,'file&id='+str(fid));check(s==403,'領収書のURLを知っていても参加者は取得不可')
        s,b=request(owner,'file&id='+str(fid));check(s==200 and b==receipt,'幹事だけが領収書をダウンロード')
        s,b=post(organizer,'admin_accounting','approve_expense',{'id':eid,'reason':'自己承認試験'});check(s==400,'自分の経費の承認を拒否')
        s,b=post(owner,'admin_accounting','approve_expense',{'id':eid,'reason':'別幹事で確認'});check(s==200,'別の幹事が承認')
        csv='管理情報\n日付,摘要,摘要内容,支払い金額,預かり金額,差引残高,メモ,未資金化区分,入払区分\n20261029,出金,印刷費,1000,,9000,,,出金\n'.encode()
        s,b=post(owner,'admin_bank','import',{}, {'csv':('bank.csv','text/csv',csv)});check(s==200,'ブラウザーと同じmultipartで銀行CSV取込')
        s,b=post(owner,'admin_bank','bank_decide',{'id':1,'state':'confirmed','reason':'原票の確認'});check(s==200,'出金を手動で確認')
        s,b=post(owner,'admin_bank','allocate',{'bank_id':1,'expense_id':eid,'amount':1000,'reason':'領収書と一致'});check(s==200,'出金と経費を照合')
        s,b=post(owner,'admin_bank','allocate',{'bank_id':1,'expense_id':eid,'amount':1,'reason':'二重試験'});check(s==400,'同じ出金の二重割当をHTTPでも拒否')
        s,b=request(owner,'export&kind=finance');check(s==200 and b.startswith(b'\xef\xbb\xbf'),'収支CSV出力')
        def chunk(k,d):return struct.pack('>I',len(d))+k+d+struct.pack('>I',zlib.crc32(k+d)&0xffffffff)
        png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',2,2,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00\xff\x00\x00\xff\x00\x00'*2))+chunk(b'IEND',b'')
        s,b=post(owner,'admin_photos','photo',{'class':3},{'photo':('sample.png','image/png',png)});check(s==200,'GDで画像を再エンコードし非公開登録')
        photoid=con.execute("select max(id) from private_files where kind='photo'").fetchone()[0]
        s,b=request(participant,'file&id='+str(photoid));check(s==403,'非公開写真の直接URLを拒否')
        s,b=post(owner,'admin_photos','publish',{'id':photoid,'reason':'見本公開'});check(s==200,'幹事が写真を公開')
        s,b=request(participant,'file&id='+str(photoid));check(s==200 and b.startswith(b'\xff\xd8'),'ログイン参加者が写真をダウンロード')
        s,b=request(anon,'file&id='+str(photoid));check(s==403,'公開写真でも未ログインの直接取得は拒否')
        s,b=post(owner,'admin_photos','publish',{'id':photoid,'reason':'見本取下げ'});check(s==200,'写真公開取り下げ')
        s,b=request(participant,'file&id='+str(photoid));check(s==403,'取り下げ後は同じURLでも拒否')

        s,b=post(owner,'admin_bank','import',{}, {'csv':('shiftjis.csv','text/csv',csv.decode().encode('cp932'))});check(s==200,'Shift-JISの銀行CSVを実際に取込')
        # Public inquiries, protected replies, class contacts and scene albums.
        s,b=request(anon,'home');check(s==200 and b'name="submission"' in b,'未ログインのトップに問い合わせフォーム')
        nonce=re.search(rb'name="submission" value="([a-f0-9]+)"',b)[1].decode()
        inquiry={'class':'0','name':'架空の質問者','email':'reply@example.test','subject':'問い合わせ <script>','body':'秘密の見本本文','submission':nonce}
        s,b=post(anon,'home','contact',inquiry);check(s==200 and '受け付けました'.encode() in b,'未ログインから問い合わせ受付')
        check(con.execute('select class_no from inquiries where id=1').fetchone()[0]==0,'クラスを忘れたの選択をDB保存')
        s,b=request(participant,'admin_inquiries&id=1');check(s==403 and '秘密の見本本文'.encode() not in b,'参加者から問い合わせ管理を拒否')
        s,b=request(owner,'admin_inquiries&id=1');check(s==200 and 'クラスを忘れた'.encode() in b and b'&lt;script&gt;' in b and '秘密の見本本文'.encode() in b,'幹事が本文確認・XSS文字をエスケープ')
        key=re.search(rb'name="reply_key" value="([a-f0-9]+)"',b)[1].decode()
        s,b=post(owner,'admin_inquiries&id=1','inquiry_reply',{'id':1,'body':'架空の回答です','reply_key':key,'to_email':'forged@example.test'})
        check(s==200 and '架空の回答です'.encode() in b,'幹事から返信を保存・履歴表示')
        s,b=request(owner,'admin_mail');check(s==200 and b'reply@example.test' in b and b'forged@example.test' not in b and '開発用・未送信'.encode() in b,'元の返信先へ開発メール記録・外部送信なし')
        s,b=request(participant,'home');nonce=re.search(rb'name="submission" value="([a-f0-9]+)"',b)[1].decode()
        s,b=post(participant,'home','contact',{**inquiry,'submission':nonce});check(s==200 and con.execute('select user_id from inquiries where id=2').fetchone()[0]==3,'ログイン中もトップから問い合わせ可能')
        s,b=post(owner,'admin_contacts&class=3','contact_add',{'class':3,'user_id':3,'position':1,'reason':'見本担当指定'});check(s==200 and '表示指定を解除'.encode() in b,'代表幹事がクラス幹事の表示を指定')
        s,b=request(participant,'my');check('3年3組のクラス幹事'.encode() in b and con.execute('select role from users where id=3').fetchone()[0]=='participant','本人の画面にクラス幹事・管理権限を増やさない')
        s,b=request(anon,'classes&class=3');check(s==200 and b'name-card' in b and b'sample3@example.test' not in b,'名簿は名前カードと人数のみ公開')
        s,b=post(owner,'admin_photos','album_save',{'name':'二次会の思い出','description':'見本','position':4,'visible':'yes','reason':'追加'});check(s==200,'種類別アルバムを追加')
        aid=con.execute('select max(id) from albums').fetchone()[0]
        s,b=post(owner,'admin_photos','photo_move',{'id':photoid,'album_id':aid,'reason':'場面を分類'});check(s==200,'既存写真を別アルバムへ移動')
        post(owner,'admin_photos','publish',{'id':photoid,'reason':'見本再公開'})
        s,b=request(participant,'gallery');check(s==200 and '二次会の思い出'.encode() in b,'参加者は場面ごとのアルバムを閲覧')
        post(owner,'admin_photos','album_save',{'id':aid,'name':'二次会の思い出','description':'見本','position':4,'visible':'no','reason':'非表示'})
        s,b=request(participant,'file&id='+str(photoid));check(s==403,'非表示アルバムの写真を直接URLでも拒否')
        s,b=request(participant,'gallery');check('二次会の思い出'.encode() not in b,'非表示アルバムを参加者の一覧から除外')
        srv.terminate();srv.wait();srv=subprocess.Popen([php,'-S','127.0.0.1:8791','-t',str(root/'public'),str(root/'public/router.php')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        time.sleep(.3);s,b=request(login(1),'admin_accounting');check(s==200 and '架空店'.encode() in b,'再起動後も経費とDBが残る')
        print('ALL',n,'HTTP CHECKS PASSED')
    finally:srv.terminate();srv.wait()
