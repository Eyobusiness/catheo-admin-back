/**
 * Catheo API REST V1 - Exhaustive TypeScript Data Transfer Objects (DTOs) & Form Models
 * Conforme à 100% au prototype officiel Cathéo Admin
 * Compatible Angular 17+ / RxJS / Reactive Forms
 */

// Global API Generic Response Wrappers
export interface ApiResponse<T> {
  status: 'success' | 'error';
  message?: string;
  data: T;
  kpis?: Record<string, any>;
  meta?: PaginationMeta;
}

export interface ErrorResponseDto {
  status: 'error';
  message: string;
}

export interface ValidationErrorResponseDto {
  status: 'error';
  message: string;
  errors: Record<string, string[]>;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// ==========================================
// 1. AUTHENTIFICATION & COMPTES UTILISATEURS
// ==========================================

// --- AUTHENTIFICATION GOUVERNÉE (ADMIN, ANIMATEURS, PARENTS) ---

export interface LoginRequestDto {
  login?: string;
  email?: string;
  password: string;
  device_name?: string;
}

export interface AdminLoginRequestDto {
  login: string; // Email, Téléphone ou Username
  password: string;
  device_name?: string;
}

export interface AnimateurLoginRequestDto {
  login: string; // Téléphone, Email ou Matricule
  password: string;
  device_name?: string;
}

export interface ParentLoginRequestDto {
  login: string; // Code Catéchumène (Matricule) ou Téléphone Parent/Tuteur
  password: string; // Mot de passe ou Code PIN d'accès
  device_name?: string;
}

export interface RefreshTokenRequestDto {
  device_name?: string;
}

export interface ChangePasswordRequestDto {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface ForgotPasswordRequestDto {
  email: string;
}

export interface VerifyResetCodeRequestDto {
  email: string;
  code: string;
}

export interface ResetPasswordRequestDto {
  email: string;
  code: string;
  password: string;
  password_confirmation: string;
  device_name?: string;
}

export interface LoginResponseDto {
  token: string;
  token_type: string;
  user_type?: 'admin' | 'animateur' | 'parent';
  user: UserDto;
  menus?: AccessibleMenuDto[];
}

export interface AdminLoginResponseDto {
  token: string;
  token_type: string;
  user_type: 'admin';
  user: UserDto;
  menus?: AccessibleMenuDto[];
}

export interface AnimateurAuthDto {
  id: string;
  matricule?: string;
  nom: string;
  prenoms: string;
  sexe: string;
  telephone?: string;
  email?: string;
  profession?: string;
  statut: string;
  created_at?: string;
}

export interface AnimateurLoginResponseDto {
  token: string;
  token_type: string;
  user_type: 'animateur';
  user: AnimateurAuthDto;
  menus?: AccessibleMenuDto[];
}

export interface CatechumeneParentAuthDto {
  id: string;
  code_catechumene: string;
  nom: string;
  prenoms: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  telephone?: string;
  nom_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise: boolean;
  statut: string;
  created_at?: string;
}

export interface ParentLoginResponseDto {
  token: string;
  token_type: string;
  user_type: 'parent';
  user: CatechumeneParentAuthDto;
  menus?: AccessibleMenuDto[];
}

export interface UserDto {
  id: string;
  name: string;
  email: string;
  telephone?: string;
  statut: 'actif' | 'inactif' | 'suspendu';
  profil?: ProfilDto;
  paroisse?: ParoisseConfigurationDto;
  created_at: string;
}

export interface CreateUserDto {
  name: string;
  email: string;
  password: string;
  telephone?: string;
  profil_id: string;
}

export interface UpdateUserDto {
  name?: string;
  email?: string;
  password?: string;
  telephone?: string;
  profil_id?: string;
}

export interface UpdateUserStatusDto {
  statut: 'actif' | 'inactif' | 'suspendu';
}

// --- MENUS & PERMISSIONS MATRICE ---

export interface MenuActionFlagsDto {
  create: boolean;
  read: boolean;
  update: boolean;
  delete: boolean;
  restore: boolean;
  force_delete: boolean;
}

export interface MenuDto {
  id: string;
  uuid: string;
  libelle: string;
  icon?: string;
  path?: string;
  reference: string;
  ordre: number;
  is_active: boolean;
  parent_id?: string;
  sousMenus?: MenuDto[];
}

export interface AccessibleMenuDto {
  uuid: string;
  libelle: string;
  icon?: string;
  path?: string;
  reference: string;
  ordre: number;
  permissions: MenuActionFlagsDto;
  sousMenus?: AccessibleMenuDto[];
}

export interface ProfilMenuPermissionDto {
  uuid?: string;
  profil_id: string;
  menu_id: string;
  can_read: boolean;
  can_create: boolean;
  can_update: boolean;
  can_delete: boolean;
  can_restore: boolean;
  can_force_delete: boolean;
}

export interface ProfilDto {
  id: string;
  uuid?: string;
  nom: string;
  code: string;
  description?: string;
  statut?: 'Actif' | 'Inactif' | 'actif' | 'inactif';
  statut_code?: 'actif' | 'inactif';
  permissions: string[];
  total_permissions?: number;
  is_system: boolean;
  users_count?: number;
  menus?: AccessibleMenuDto[];
  created_at?: string;
}

export interface PermissionItemDto {
  key: string;
  label: string;
}

export interface SubMenuPermissionDto {
  nom: string;
  code: string;
  permissions: PermissionItemDto[];
}

export interface PermissionTreeNodeDto {
  menu: string;
  code: string;
  permissions?: PermissionItemDto[];
  sous_menus?: SubMenuPermissionDto[];
}

export interface CreateProfilDto {
  nom: string;
  description?: string;
  permissions: string[];
}

export interface UpdateProfilDto {
  nom?: string;
  description?: string;
  permissions?: string[];
}

// ==========================================
// 2. CONFIGURATION PAROISSIALE
// ==========================================

export interface ParoisseConfigurationDto {
  id: string;
  nom: string;
  code_paroisse: string;
  diocese?: string;
  doyenne?: string;
  ville?: string;
  commune?: string;
  telephone?: string;
  email?: string;
  site_web?: string;
  adresse?: string;
  logo_url?: string;
  cure_nom?: string;
  coordination_nom?: string;
  statut: 'actif' | 'inactif' | 'suspendu';
}

export interface UpdateParoisseConfigurationDto {
  nom?: string;
  diocese?: string;
  doyenne?: string;
  ville?: string;
  commune?: string;
  telephone?: string;
  email?: string;
  site_web?: string;
  adresse?: string;
  cure_nom?: string;
  coordination_nom?: string;
}

export interface ApparenceConfigurationDto {
  id: string;
  couleur_principale: string; // Ex: "#4F46E5"
  couleur_secondaire: string; // Ex: "#D97706"
  police_caracteres: 'Inter' | 'Roboto' | 'Outfit' | 'Poppins' | 'Nunito' | 'DM Sans';
  logo_url?: string;
  entete_document?: string;
  pied_page_document?: string;
  updated_at?: string;
}

export interface UpdateApparenceConfigurationDto {
  couleur_principale?: string;
  couleur_secondaire?: string;
  police_caracteres?: 'Inter' | 'Roboto' | 'Outfit' | 'Poppins' | 'Nunito' | 'DM Sans';
  logo_url?: string;
  entete_document?: string;
  pied_page_document?: string;
}

export interface SauvegardeDto {
  id: string;
  nom_fichier: string;
  date: string; // Ex: "10/08/2026"
  heure: string; // Ex: "00:46"
  taille: string; // Ex: "13.1 MB"
  taille_octets: number;
  cree_par: string; // Ex: "Abbé Ferdinand" ou "Système (Auto)"
  type: 'manuel' | 'automatique';
  statut: 'termine' | 'en_cours' | 'echec';
  created_at?: string;
}

export interface ResponsableParoisseDto {
  id: string;
  nom_prenoms: string;
  fonction: string;
  titre?: string;
  telephone?: string;
  email?: string;
  signature_url?: string;
  ordre_affichage?: number;
}

export interface CreateResponsableParoisseDto {
  nom_prenoms: string;
  fonction: string;
  titre?: string;
  telephone?: string;
  email?: string;
  signature_url?: string;
  ordre_affichage?: number;
}

export interface UpdateResponsableParoisseDto {
  nom_prenoms?: string;
  fonction?: string;
  titre?: string;
  telephone?: string;
  email?: string;
  signature_url?: string;
  ordre_affichage?: number;
}

// ==========================================
// 3. ORGANISATION PASTORALE & CALENDRIER
// ==========================================

export interface AnneeCatecheseDto {
  id: string;
  libelle: string;
  date_debut: string;
  date_fin: string;
  est_active: boolean;
  statut: 'active' | 'cloturee';
}

export interface CreateAnneeCatecheseDto {
  libelle: string;
  date_debut: string;
  date_fin: string;
}
export interface SectionDto {
  id: string;
  nom: string;
  code?: string;
  description?: string;
  statut: 'Actif' | 'Inactif';
  statut_code: 'actif' | 'inactif';
  ordre_affichage?: number;
  niveaux_count?: number;
}

export interface CreateSectionDto {
  nom: string;
  code: string;
  description?: string;
  statut?: 'actif' | 'inactif';
  ordre_affichage?: number;
}

export interface UpdateSectionDto {
  nom?: string;
  code?: string;
  description?: string;
  statut?: 'actif' | 'inactif';
  ordre_affichage?: number;
}

export interface NiveauDto {
  id: string;
  nom: string;
  
  description?: string;
  statut: 'Actif' | 'Inactif';
  statut_code: 'actif' | 'inactif';
  
  ordre_affichage?: number;
  section?: SectionDto;
}

export interface CreateNiveauDto {
  section_id: string;
  nom: string;
  
  description?: string;
  statut?: 'actif' | 'inactif';
  ordre_affichage?: number;
}

export interface UpdateNiveauDto {
  section_id?: string;
  nom?: string;
  
  description?: string;
  statut?: 'actif' | 'inactif';
 
  ordre_affichage?: number;
}

export interface ClasseDto {
  id: string;
  nom: string;
  capacite_max: number;
  statut: 'active' | 'inactive';
  niveau?: NiveauDto;
  annee_catechese?: AnneeCatecheseDto;
  effectif_actuel?: number;
}

export interface CreateClasseDto {
  niveau_id: string;
  annee_catechese_id?: string;
  nom: string;
  capacite_max?: number; 
}

export interface AnimateurDto {
  id: string;
  matricule?: string;
  nom: string;
  prenoms: string;
  sexe: 'M' | 'F';
  telephone?: string;
  email?: string;
  profession?: string;
  statut: 'actif' | 'inactif';
  user?: UserDto;
}

export interface CreateAnimateurDto {
  nom: string;
  prenoms: string;
  sexe: 'M' | 'F';
  telephone?: string;
  email?: string;
  profession?: string;
  create_user_account?: boolean;
}

export interface UpdateAnimateurStatusDto {
  statut: 'actif' | 'inactif';
}

export interface AffectationAnimateurDto {
  id: string;
  animateur: AnimateurDto;
  classe: ClasseDto;
  role: 'principal' | 'adjoint' | 'assistant';
  date_affectation: string;
}

export interface CreateAffectationAnimateurDto {
  animateur_id: string;
  classe_id: string;
  role?: 'principal' | 'adjoint' | 'assistant';
}

export interface ModuleTrimestrielDto {
  id: string;
  trimestre: 'T1' | 'T2' | 'T3';
  libelle: string;
  date_debut: string;
  date_fin: string;
  
}

export interface CreateModuleTrimestrielDto {
  annee_catechese_id: string;
  trimestre: 'T1' | 'T2' | 'T3';
  libelle: string;
  date_debut: string;
  date_fin: string;
  
}

// ──────────────────────────────────────────
// CEB (Communautés Ecclésiales de Base)
// ──────────────────────────────────────────
export interface CebDto {
  id: string;
  nom: string;
  responsable?: string;
  telephone?: string;
  adresse?: string;
  description?: string;
  statut: 'Active' | 'Inactive';
  statut_code: 'active' | 'inactive';
  total_inscriptions?: number;
  created_at?: string;
}

export interface CreateCebDto {
  nom: string;
  responsable?: string;
  telephone?: string;
  adresse?: string;
  description?: string;
  statut?: 'Active' | 'Inactive' | 'active' | 'inactive';
}

export interface UpdateCebDto {
  nom?: string;
  responsable?: string;
  telephone?: string;
  adresse?: string;
  description?: string;
  statut?: 'Active' | 'Inactive' | 'active' | 'inactive';
}

// ──────────────────────────────────────────
// Mouvements Paroissiaux
// ──────────────────────────────────────────
export interface MouvementDto {
  id: string;
  nom: string;
  responsable?: string;
  telephone?: string;
  description?: string;
  statut: 'Active' | 'Inactive';
  statut_code: 'active' | 'inactive';
  total_inscriptions?: number;
  created_at?: string;
}

export interface CreateMouvementDto {
  nom: string;
  responsable?: string;
  telephone?: string;
  description?: string;
  statut?: 'Active' | 'Inactive' | 'active' | 'inactive';
}

export interface UpdateMouvementDto {
  nom?: string;
  responsable?: string;
  telephone?: string;
  description?: string;
  statut?: 'Active' | 'Inactive' | 'active' | 'inactive';
}

// ──────────────────────────────────────────
// Calendrier Pastoral
// ──────────────────────────────────────────
export type CibleTypeCalendrier = 'TOUS' | 'ANIMATEURS' | 'SECTION' | 'NIVEAU' | 'CLASSE' | 'CEB' | 'MOUVEMENT';
export type StatutCalendrier = 'Planifié' | 'Réalisé' | 'Annulé' | 'planifie' | 'realise' | 'annule';

export interface CalendrierDto {
  id: string;
  titre: string;
  type: string;
  date: string;
  heure_debut?: string;
  heure_fin?: string;
  lieu?: string;
  cible_type: CibleTypeCalendrier;
  cible_id?: string;
  cible_nom?: string;
  description?: string;
  statut: 'Planifié' | 'Réalisé' | 'Annulé';
  annee_catechese?: AnneeCatecheseDto;
  created_at?: string;
}

export interface CreateCalendrierDto {
  annee_catechese_id?: string;
  titre: string;
  type: string;
  date: string;
  heure_debut?: string;
  heure_fin?: string;
  lieu?: string;
  cible_type?: CibleTypeCalendrier;
  cible_id?: string;
  description?: string;
  statut?: StatutCalendrier;
}

export interface UpdateCalendrierDto {
  annee_catechese_id?: string;
  titre?: string;
  type?: string;
  date?: string;
  heure_debut?: string;
  heure_fin?: string;
  lieu?: string;
  cible_type?: CibleTypeCalendrier;
  cible_id?: string;
  description?: string;
  statut?: StatutCalendrier;
}

// ==========================================
// 4. PREINSCRIPTIONS & CATÉCHUMÈNES
// ==========================================

export interface CampagnePreinscriptionDto {
  id: string;
  titre: string;
  nom?: string;
  date_debut: string;
  date_fin: string;
  statut: 'ouverte' | 'fermee' | 'suspendue';
  est_ouverte: boolean;
  description?: string;
  sections_autorisees?: string[];
  public_url?: string;
  qr_code_url?: string;
  annee_catechese?: AnneeCatecheseDto;
  preinscriptions_count?: number;
  created_at?: string;
}

export interface CreateCampagnePreinscriptionDto {
  annee_catechese_id: string;
  titre: string;
  date_debut: string;
  date_fin: string;
  sections_autorisees?: string[];
  description?: string;
  statut?: 'ouverte' | 'fermee' | 'suspendue';
}

export interface UpdateCampagnePreinscriptionDto {
  titre?: string;
  date_debut?: string;
  date_fin?: string;
  sections_autorisees?: string[];
  description?: string;
  statut?: 'ouverte' | 'fermee' | 'suspendue';
}

export interface PreinscriptionDto {
  id: string;
  code_dossier: string;
  type_demande: 'nouvelle_inscription' | 'reinscription' | 'premiere_inscription';
  nom: string;
  prenoms: string;
  nom_complet?: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  lieu_naissance?: string;
  adresse?: string;
  telephone?: string;
  photo_url?: string;
  situation_matrimoniale?: string;
  nom_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise: boolean;
  date_bapteme?: string;
  lieu_bapteme?: string;
  paroisse_bapteme?: string;
  nom_parrain?: string;
  sexe_parrain?: 'M' | 'F';
  telephone_parrain?: string;
  acte_naissance_url?: string;
  statut: 'en_attente' | 'validee' | 'rejetee' | 'a_affecter';
  notes_validation?: string;
  campagne?: CampagnePreinscriptionDto;
  annee_catechese?: AnneeCatecheseDto;
  section_souhaite?: SectionDto;
  niveau_souhaite?: NiveauDto;
  created_at: string;
}

export interface SubmitPreinscriptionDto {
  campagne_id: string;
  section_souhaite_id?: string;
  niveau_souhaite_id?: string;
  type_demande?: 'nouvelle_inscription' | 'reinscription' | 'premiere_inscription';
  nom: string;
  prenoms: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  lieu_naissance?: string;
  adresse?: string;
  telephone?: string;
  photo_url?: string;
  situation_matrimoniale?: string;
  nom_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise?: boolean;
  date_bapteme?: string;
  lieu_bapteme?: string;
  paroisse_bapteme?: string;
  nom_parrain?: string;
  sexe_parrain?: 'M' | 'F';
  telephone_parrain?: string;
  acte_naissance_url?: string;
}

export interface ValiderPreinscriptionDto {
  niveau_id: string;
  classe_id?: string;
  frais_payes?: boolean;
  notes_validation?: string;
}

export interface RejeterPreinscriptionDto {
  motif?: string;
}

export interface CatechumeneDto {
  id: string;
  code_catechumene: string;
  matricule?: string;
  nom: string;
  prenoms: string;
  nom_complet?: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  lieu_naissance?: string;
  adresse?: string;
  domicile?: string;
  profession?: string;
  classe_scolaire?: string;
  situation_matrimoniale?: string;
  telephone?: string;
  photo_path?: string;
  photo_url?: string;
  nom_pere?: string;
  origine_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  origine_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise: boolean;
  num_carnet_bapteme?: string;
  date_bapteme?: string;
  lieu_bapteme?: string;
  diocese_bapteme?: string;
  ville_bapteme?: string;
  paroisse_bapteme?: string;
  date_premiere_communion?: string;
  paroisse_premiere_communion?: string;
  date_confirmation?: string;
  paroisse_confirmation?: string;
  ministre_confirmation?: string;
  statut: 'actif' | 'abandon' | 'transfere' | 'complete';
  ceb?: CebDto;
  inscriptions_annuelles?: InscriptionAnnuelleDto[];
  parrains_marraines?: ParrainMarraineDto[];
  created_at: string;
}

export interface CreateCatechumeneDto {
  ceb_id?: string;
  nom: string;
  prenoms: string;
  sexe: 'M' | 'F';
  date_naissance: string;
  lieu_naissance?: string;
  adresse?: string;
  domicile?: string;
  profession?: string;
  classe_scolaire?: string;
  situation_matrimoniale?: string;
  telephone?: string;
  photo_url?: string;
  nom_pere?: string;
  origine_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  origine_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise?: boolean;
  num_carnet_bapteme?: string;
  date_bapteme?: string;
  lieu_bapteme?: string;
  diocese_bapteme?: string;
  ville_bapteme?: string;
  paroisse_bapteme?: string;
  date_premiere_communion?: string;
  paroisse_premiere_communion?: string;
  date_confirmation?: string;
  paroisse_confirmation?: string;
  ministre_confirmation?: string;
  statut?: 'actif' | 'abandon' | 'transfere' | 'complete';
}

export interface UpdateCatechumeneDto {
  ceb_id?: string;
  nom?: string;
  prenoms?: string;
  sexe?: 'M' | 'F';
  date_naissance?: string;
  lieu_naissance?: string;
  adresse?: string;
  domicile?: string;
  profession?: string;
  classe_scolaire?: string;
  situation_matrimoniale?: string;
  telephone?: string;
  photo_url?: string;
  nom_pere?: string;
  origine_pere?: string;
  telephone_pere?: string;
  nom_mere?: string;
  origine_mere?: string;
  telephone_mere?: string;
  nom_tuteur?: string;
  telephone_tuteur?: string;
  est_baptise?: boolean;
  num_carnet_bapteme?: string;
  date_bapteme?: string;
  lieu_bapteme?: string;
  diocese_bapteme?: string;
  ville_bapteme?: string;
  paroisse_bapteme?: string;
  date_premiere_communion?: string;
  paroisse_premiere_communion?: string;
  date_confirmation?: string;
  paroisse_confirmation?: string;
  ministre_confirmation?: string;
  statut?: 'actif' | 'abandon' | 'transfere' | 'complete';
}

export interface InscriptionAnnuelleDto {
  id: string;
  code_inscription?: string;
  date_inscription: string;
  statut_inscription: 'inscrit' | 'valide' | 'en_attente' | 'abandon';
  frais_inscription_payes: boolean;
  observation?: string;
  catechumene?: CatechumeneDto;
  annee_catechese?: AnneeCatecheseDto;
  section?: SectionDto;
  niveau?: NiveauDto;
  classe?: ClasseDto;
  ceb?: CebDto;
  mouvement?: MouvementDto;
  created_at?: string;
}

export interface CreateInscriptionAnnuelleDto {
  catechumene_id: string;
  annee_catechese_id: string;
  section_id?: string;
  niveau_id: string;
  classe_id?: string;
  ceb_id?: string;
  mouvement_id?: string;
  date_inscription?: string;
  frais_inscription_payes?: boolean;
  observation?: string;
}

export interface UpdateInscriptionAnnuelleDto {
  section_id?: string;
  niveau_id?: string;
  classe_id?: string;
  ceb_id?: string;
  mouvement_id?: string;
  statut_inscription?: 'inscrit' | 'valide' | 'en_attente' | 'abandon';
  frais_inscription_payes?: boolean;
  observation?: string;
}

export interface ParrainMarraineDto {
  id: string;
  type: 'parrain' | 'marraine';
  nom_prenoms: string;
  telephone?: string;
  email?: string;
  domicile?: string;
  paroisse_origine?: string;
  representant_nom?: string;
  representant_contact?: string;
  sacrement_confirmation: boolean;
  catechumene?: CatechumeneDto;
  created_at?: string;
}

export interface CreateParrainMarraineDto {
  catechumene_id: string;
  type: 'parrain' | 'marraine';
  nom_prenoms: string;
  telephone?: string;
  email?: string;
  domicile?: string;
  paroisse_origine?: string;
  representant_nom?: string;
  representant_contact?: string;
  sacrement_confirmation?: boolean;
}

export interface UpdateParrainMarraineDto {
  type?: 'parrain' | 'marraine';
  nom_prenoms?: string;
  telephone?: string;
  email?: string;
  domicile?: string;
  paroisse_origine?: string;
  representant_nom?: string;
  representant_contact?: string;
  sacrement_confirmation?: boolean;
}

export interface MutationCatechumeneDto {
  id: string;
  paroisse_origine_nom: string;
  paroisse_destination_nom: string;
  motif?: string;
  date_mutation: string;
  statut: 'demande' | 'approuve' | 'refuse';
  catechumene?: CatechumeneDto;
  annee_catechese?: AnneeCatecheseDto;
  created_at?: string;
}

export interface CreateMutationCatechumeneDto {
  catechumene_id: string;
  annee_catechese_id: string;
  paroisse_origine_nom: string;
  paroisse_destination_nom: string;
  motif?: string;
  date_mutation: string;
}

export interface UpdateMutationCatechumeneDto {
  statut: 'approuve' | 'refuse';
}

// ==========================================
// 5. SÉANCES, PRÉSENCES & ÉVALUATIONS
// ==========================================

export interface SeanceDto {
  id: string;
  titre_lecon: string;
  date_seance: string;
  duree_minutes: number;
  classe?: ClasseDto;
  animateur?: AnimateurDto;
  total_presences?: number;
}

export interface CreateSeanceDto {
  classe_id: string;
  titre_lecon: string;
  date_seance: string;
  duree_minutes?: number;
}

export interface RecordPresencesBatchDto {
  presences: {
    catechumene_id: string;
    est_present: boolean;
    motif_absence?: string;
  }[];
}

export interface EvaluationStatsDto {
  moyenne_classe: number;
  plus_forte_note: number;
  plus_faible_note: number;
  saisies_effectuees: number;
  total_eleves: number;
  saisies_ratio: string;
}

export interface EvaluationDto {
  id: string;
  titre: string;
  type_eval: string;
  type_eval_code: string;
  coefficient: number;
  note_max: number;
  date_evaluation?: string;
  stats?: EvaluationStatsDto;
  annee_catechese?: AnneeCatecheseDto;
  module_trimestriel?: ModuleTrimestrielDto;
  classe?: ClasseDto;
  notes?: NoteDto[];
  created_at?: string;
}

export interface NoteDto {
  id: string;
  catechumene_id?: string;
  code_catechumene?: string;
  nom_prenoms?: string;
  note_obtenue: number;
  appreciation?: string;
  catechumene?: CatechumeneDto;
  created_at?: string;
}

export interface NoteItemGridDto {
  catechumene_id: string;
  code_catechumene: string;
  nom: string;
  prenoms: string;
  nom_prenoms: string;
  note_obtenue: number | null;
  appreciation: string | null;
  note_id?: string;
}

export interface CreateEvaluationDto {
  annee_catechese_id: string;
  module_trimestriel_id: string;
  classe_id: string;
  titre: string;
  type_eval: 'devoir' | 'interrogation' | 'examen' | 'comportement';
  coefficient?: number;
  note_max?: number;
  date_evaluation?: string;
}

export interface RecordNotesBatchDto {
  notes: {
    catechumene_id: string;
    note_obtenue: number;
    appreciation?: string;
  }[];
}

export interface BulletinTrimestrielDto {
  id: string;
  moyenne_connaissances: number;
  moyenne_comportement: number;
  moyenne_generale: number;
  rang: number;
  appreciation_generale?: string;
  decision?: 'admis' | 'rattrapage' | 'redouble';
  catechumene?: CatechumeneDto;
  module_trimestriel?: ModuleTrimestrielDto;
}

export interface CalculerBulletinDto {
  classe_id: string;
  module_trimestriel_id: string;
}

export interface DecisionFinAnneeDto {
  id: string;
  decision_finale: 'admis_niveau_superieur' | 'redoublement' | 'reorientation';
  observations?: string;
  catechumene?: CatechumeneDto;
}

export interface CreateDecisionFinAnneeDto {
  catechumene_id: string;
  annee_catechese_id?: string;
  decision_finale: 'admis_niveau_superieur' | 'redoublement' | 'reorientation';
  observations?: string;
}

// ====================================================================================
// 6. FINANCES & CAISSE (Conforme à 100% aux 4 écrans du prototype Cathéo Admin)
// ====================================================================================

// SCREEN 1 — Opérations (Paiements en attente)
export interface OperationPaiementDto {
  id: string;
  reference: string; // OP-2026-001
  libelle: string; // Inscription annuelle Catéchèse
  montant: number;
  montant_paye: number;
  echeance?: string;
  statut: 'en_attente' | 'partiellement_paye' | 'paye' | 'annule';
  catechumene?: CatechumeneDto;
  tarif?: TarifDto;
}

export interface CreateOperationPaiementDto {
  annee_catechese_id?: string;
  catechumene_id?: string;
  tarif_id?: string;
  libelle: string;
  montant: number;
  echeance?: string;
}

// SCREEN 2 — Caisse (Paiements encaissés & KPIs Trésorerie)
export interface CaisseParoissialeDto {
  id: string;
  reference?: string; // ENC-2026-0041
  date_mouvement: string;
  libelle: string;
  montant: number;
  mode_paiement?: string;
  caissier_nom?: string;
  solde_apres: number;
  type_mouvement: 'entree' | 'recette' | 'sortie' | 'depense' | 'remboursement';
}

export interface CaisseKpiDto {
  solde_en_caisse: number;
  total_encaisse: number;
  total_rembourse: number;
  paiements_valides_count: number;
}

export interface RemboursementRequestDto {
  montant_rembourse?: number;
  motif: string;
}

// SCREEN 3 — Versements à la Paroisse / au Curé (Reversements des recettes)
export interface VersementCureDto {
  id: string;
  reference: string; // VRS-2026-001
  periode_concernee: string; // Juin 2026
  montant_verse: number;
  mode_remise: 'cheque' | 'especes' | 'virement';
  effectue_par?: string;
  statut: 'valide' | 'en_attente' | 'annule';
  user?: UserDto;
  created_at: string;
}

export interface CreateVersementCureDto {
  annee_catechese_id?: string;
  periode_concernee: string;
  montant_verse: number;
  mode_remise: 'cheque' | 'especes' | 'virement';
  effectue_par?: string;
}

export interface VersementCureKpiDto {
  total_en_caisse: number;
  total_deja_verse: number;
  reste_a_reverser: number;
}

// SCREEN 4 — Configuration des paiements (Tarifs par niveau)
export interface TarifDto {
  id: string;
  intitule: string;
  description?: string;
  montant: number;
  periode_debut?: string;
  periode_fin?: string;
  est_obligatoire: boolean;
  type_tarif: 'inscription' | 'manuel' | 'uniforme' | 'examen' | 'retraite' | 'autre';
  statut: 'actif' | 'inactif';
  niveaux?: NiveauDto[];
}

export interface CreateTarifDto {
  intitule: string;
  description?: string;
  montant: number;
  periode_debut?: string;
  periode_fin?: string;
  est_obligatoire?: boolean;
  type_tarif?: 'inscription' | 'manuel' | 'uniforme' | 'examen' | 'retraite' | 'autre';
  niveau_uuids?: string[];
}

// ==========================================
// 7. COMMUNICATION, NOTIFICATIONS & AUDIT
// ==========================================

export interface AnnonceDto {
  id: string;
  titre: string;
  contenu: string;
  canal: 'sms' | 'whatsapp' | 'email' | 'affichage';
  statut: 'brouillon' | 'programmee' | 'envoyee';
  date_diffusion?: string;
}

export interface CreateAnnonceDto {
  titre: string;
  contenu: string;
  canal?: 'sms' | 'whatsapp' | 'email' | 'affichage';
  date_diffusion?: string;
}

export interface NotificationLogDto {
  id: string;
  destinataire_contact: string;
  type_canal: string;
  message: string;
  statut_envoi: 'succes' | 'echec';
  date_envoi: string;
}

export interface AuditLogDto {
  id: string;
  action: string;
  description?: string;
  ip_address?: string;
  user?: UserDto;
  created_at: string;
}

// ==========================================
// 8. DASHBOARD & IMPRESSIONS & EXPORTS
// ==========================================

export interface DashboardSummaryDto {
  catechumenes_total: number;
  nouveaux_ce_mois: number;
  inscriptions_valides: number;
  preinscriptions_en_attente: number;
  animateurs_actifs: number;
  classes_ouvertes: number;
  recettes_du_mois: number;
  taux_presence_global: number;
  repartition_par_section: { section: string; total: number }[];
  evolution_recettes_mensuelles: { mois: string; montant: number }[];
  dernieres_preinscriptions: PreinscriptionDto[];
}

export interface PrintHeaderDto {
  diocese: string;
  doyenne: string;
  paroisse: string;
  adresse: string;
  telephone: string;
  email: string;
  logo_url: string | null;
  coordination: string;
  annee: string;
}

export interface PrintFilterRequestDto {
  annee_catechese_id?: string;
  section_id?: string;
  niveau_id?: string;
  classe_id?: string;
  catechumene_id?: string;
  sacrament?: string;
  debut_cours?: string;
  jour?: string;
  nombre_seances?: number;
}

export interface ExportRequestDto {
  format: 'xlsx' | 'pdf' | 'csv';
  section_id?: string;
  niveau_id?: string;
  classe_id?: string;
  date_debut?: string;
  date_fin?: string;
}
